<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedOrderCompletionGuard;
use HiEvents\Exceptions\CannotAcceptPaymentException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OccurrenceStatusValidator;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\NotCompletableOrderRefundDTO;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use HiEvents\Services\Domain\Payment\Stripe\StripeRefundExpiredOrderService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Stripe\PaymentIntent;
use Tests\TestCase;
use Throwable;

final readonly class RecordingRefundExpiredOrderService extends StripeRefundExpiredOrderService
{
    public function __construct(private RefundCallLog $log) {}

    public function refundExpiredOrder(PaymentIntent $paymentIntent, StripePaymentDomainObject $stripePayment, OrderDomainObject $order, bool $notifyBuyer = true): NotCompletableOrderRefundDTO
    {
        $this->log->orderIds[] = (int) $order->getId();
        $this->log->notifyBuyer[] = $notifyBuyer;
        $this->log->steps[] = 'refund';

        return new NotCompletableOrderRefundDTO(
            orderId: (int) $order->getId(),
            refundId: 're_test',
            amount: 10.0,
            currency: 'EUR',
            status: 'succeeded',
            paymentIntentId: $paymentIntent->id,
        );
    }

    public function hasRefunded(int $orderId, string $paymentIntentId): bool
    {
        return in_array($paymentIntentId, $this->log->alreadyRefundedPaymentIntentIds, true);
    }

    public function recordRefund(NotCompletableOrderRefundDTO $refund): void
    {
        $this->log->recordedRefundIds[] = $refund->refundId;
        $this->log->recordedInsideTransaction[] = $this->log->inTransaction;
    }
}

final class RefundCallLog
{
    /** @var list<int> */
    public array $orderIds = [];

    /** @var list<bool> */
    public array $notifyBuyer = [];

    /** @var list<string> */
    public array $recordedRefundIds = [];

    /** @var list<bool> */
    public array $recordedInsideTransaction = [];

    public bool $inTransaction = false;

    /** @var list<string> */
    public array $alreadyRefundedPaymentIntentIds = [];

    /** @var list<string> */
    public array $steps = [];
}

class PaymentIntentSucceededHandlerTest extends TestCase
{
    private OrderRepositoryInterface|MockInterface $orderRepository;

    private StripePaymentsRepository|MockInterface $stripePaymentsRepository;

    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private RefundCallLog $refundLog;

    private Repository|MockInterface $cache;

    private PaymentIntentSucceededHandler $handler;

    private SeatedOrderCompletionGuard|MockInterface $seatedOrderCompletionGuard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->stripePaymentsRepository = Mockery::mock(StripePaymentsRepository::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->refundLog = new RefundCallLog;

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(function ($callback) {
            $this->refundLog->inTransaction = true;
            $this->refundLog->steps[] = 'begin';

            try {
                return $callback();
            } finally {
                $this->refundLog->inTransaction = false;
                $this->refundLog->steps[] = 'end';
            }
        });
        $databaseManager->shouldReceive('statement');

        $this->cache = Mockery::mock(Repository::class);
        $cache = $this->cache;
        $cache->shouldReceive('has')->andReturn(false)->byDefault();
        $cache->shouldReceive('put')->andReturnTrue();

        $this->seatedOrderCompletionGuard = Mockery::mock(SeatedOrderCompletionGuard::class);
        $this->seatedOrderCompletionGuard->shouldReceive('assertSeatsHeld')->byDefault();

        $this->handler = new PaymentIntentSucceededHandler(
            $this->orderRepository,
            $this->stripePaymentsRepository,
            Mockery::mock(AffiliateRepositoryInterface::class),
            Mockery::mock(ProductQuantityUpdateService::class),
            new RecordingRefundExpiredOrderService($this->refundLog),
            Mockery::mock(AttendeeRepositoryInterface::class),
            $databaseManager,
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            $cache,
            Mockery::mock(DomainEventDispatcherService::class),
            Mockery::mock(OrderApplicationFeeService::class),
            Mockery::mock(EventSettingsRepositoryInterface::class),
            new OccurrenceStatusValidator($this->occurrenceRepository),
            $this->seatedOrderCompletionGuard,
            new TransactionLockService($databaseManager),
        );
    }

    public function test_cancelled_order_is_refunded_and_not_revived(): void
    {
        $this->assertLatePaymentRefundedAndRejected(OrderStatus::CANCELLED->name);
    }

    public function test_abandoned_order_is_refunded_and_not_revived(): void
    {
        $this->assertLatePaymentRefundedAndRejected(OrderStatus::ABANDONED->name);
    }

    public function test_already_paid_cancelled_order_is_rejected_without_a_second_refund(): void
    {
        $order = (new OrderDomainObject)->setShortId('o_test')
            ->setId(1)
            ->setStatus(OrderStatus::CANCELLED->name)
            ->setPaymentStatus(OrderPaymentStatus::PAYMENT_RECEIVED->name);

        $stripePayment = (new StripePaymentDomainObject)
            ->setOrderId(1)
            ->setPaymentIntentId('pi_test')
            ->setOrder($order);

        $this->stripePaymentsRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')->andReturn($stripePayment);

        $this->orderRepository->shouldNotReceive('updateFromArray');

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
        }

        $this->assertSame([], $this->refundLog->orderIds, 'An already-paid order must not be refunded on a duplicate webhook');
    }

    public function test_late_payment_on_a_cancelled_occurrence_is_refunded_and_rejected(): void
    {
        $orderItem = (new OrderItemDomainObject)->setEventOccurrenceId(5);

        $order = (new OrderDomainObject)->setShortId('o_test')
            ->setId(1)
            ->setStatus(OrderStatus::RESERVED->name)
            ->setPaymentStatus(OrderPaymentStatus::AWAITING_PAYMENT->name);
        $order->setOrderItems(collect([$orderItem]));

        $stripePayment = (new StripePaymentDomainObject)
            ->setOrderId(1)
            ->setPaymentIntentId('pi_test')
            ->setOrder($order);

        $this->stripePaymentsRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')->andReturn($stripePayment);

        $cancelledOccurrence = (new EventOccurrenceDomainObject)->setStatus(EventOccurrenceStatus::CANCELLED->name);
        $this->occurrenceRepository
            ->shouldReceive('findWhereIn')
            ->with('id', [5])
            ->andReturn(collect([$cancelledOccurrence]));

        $this->orderRepository->shouldNotReceive('updateFromArray');

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
        }

        $this->assertSame([1], $this->refundLog->orderIds, 'A late payment on a cancelled occurrence should be refunded exactly once');
    }

    public function test_a_late_payment_that_was_already_refunded_is_not_refunded_again(): void
    {
        $orderItem = (new OrderItemDomainObject)->setEventOccurrenceId(5);

        $order = (new OrderDomainObject)->setShortId('o_test')
            ->setId(1)
            ->setStatus(OrderStatus::RESERVED->name)
            ->setPaymentStatus(OrderPaymentStatus::AWAITING_PAYMENT->name);
        $order->setOrderItems(collect([$orderItem]));

        $stripePayment = (new StripePaymentDomainObject)
            ->setOrderId(1)
            ->setPaymentIntentId('pi_test')
            ->setOrder($order);

        $this->stripePaymentsRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')->andReturn($stripePayment);

        $cancelledOccurrence = (new EventOccurrenceDomainObject)->setStatus(EventOccurrenceStatus::CANCELLED->name);
        $this->occurrenceRepository
            ->shouldReceive('findWhereIn')
            ->with('id', [5])
            ->andReturn(collect([$cancelledOccurrence]));

        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->refundLog->alreadyRefundedPaymentIntentIds[] = 'pi_test';

        $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));

        $this->assertSame([], $this->refundLog->orderIds);
        $this->assertSame([], $this->refundLog->recordedRefundIds);
    }

    public function test_late_card_tap_on_a_door_sale_already_paid_in_cash_is_refunded_quietly(): void
    {
        $order = $this->offlinePaidOrder()->setBoxOfficeId(4);
        $this->givenStripePaymentFor($order);
        $this->orderRepository->shouldNotReceive('updateFromArray');

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
        }

        $this->assertSame([1], $this->refundLog->orderIds);
        $this->assertSame([false], $this->refundLog->notifyBuyer, 'The buyer kept their cash tickets, so no expiry email');
    }

    public function test_late_card_tap_on_a_comped_door_sale_is_refunded_quietly(): void
    {
        $order = (new OrderDomainObject)->setShortId('o_test')
            ->setId(1)
            ->setBoxOfficeId(4)
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setPaymentStatus(OrderPaymentStatus::NO_PAYMENT_REQUIRED->name);
        $this->givenStripePaymentFor($order);
        $this->orderRepository->shouldNotReceive('updateFromArray');

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
        }

        $this->assertSame([1], $this->refundLog->orderIds);
        $this->assertSame([false], $this->refundLog->notifyBuyer);
    }

    public function test_late_card_tap_on_an_abandoned_door_sale_is_refunded_quietly(): void
    {
        $order = (new OrderDomainObject)->setShortId('o_test')
            ->setId(1)
            ->setBoxOfficeId(4)
            ->setStatus(OrderStatus::ABANDONED->name)
            ->setPaymentStatus(OrderPaymentStatus::AWAITING_PAYMENT->name);
        $this->givenStripePaymentFor($order);
        $this->orderRepository->shouldNotReceive('updateFromArray');

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
        }

        $this->assertSame([1], $this->refundLog->orderIds);
        $this->assertSame([false], $this->refundLog->notifyBuyer);
    }

    public function test_online_order_marked_paid_offline_is_rejected_without_a_refund(): void
    {
        $this->givenStripePaymentFor($this->offlinePaidOrder());
        $this->orderRepository->shouldNotReceive('updateFromArray');

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
        }

        $this->assertSame([], $this->refundLog->orderIds, 'Only door sales auto-refund a late card success');
    }

    public function test_expired_order_that_cannot_be_rescued_is_refunded(): void
    {
        $this->givenStripePaymentFor($this->expiredOrderAwaitingPayment());
        $this->occurrenceRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->seatedOrderCompletionGuard->shouldReceive('canRescueExpired')->once()->andReturnFalse();
        $this->orderRepository->shouldNotReceive('updateFromArray');

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
        }

        $this->assertSame([1], $this->refundLog->orderIds);
    }

    public function test_expired_seated_order_whose_seats_are_still_held_is_not_refunded(): void
    {
        $this->givenStripePaymentFor($this->expiredOrderAwaitingPayment());
        $this->occurrenceRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->seatedOrderCompletionGuard->shouldReceive('canRescueExpired')->once()->andReturnTrue();

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
        } catch (CannotAcceptPaymentException) {
            $this->fail('A rescued order must not be rejected');
        } catch (Throwable) {
        }

        $this->assertSame([], $this->refundLog->orderIds);
    }

    public function test_unexpired_order_whose_seats_are_no_longer_held_is_refunded(): void
    {
        $order = $this->expiredOrderAwaitingPayment()->setReservedUntil(now()->addMinutes(5)->toDateTimeString());
        $this->givenStripePaymentFor($order);
        $this->occurrenceRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->seatedOrderCompletionGuard->shouldNotReceive('canRescueExpired');
        $this->seatedOrderCompletionGuard->shouldReceive('assertSeatsHeld')->once()->andThrow(new ResourceConflictException('gone'));
        $this->orderRepository->shouldNotReceive('updateFromArray');

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
        }

        $this->assertSame([1], $this->refundLog->orderIds);
    }

    public function test_the_refund_is_made_after_the_seat_check_has_released_its_lock(): void
    {
        $order = $this->expiredOrderAwaitingPayment()->setReservedUntil(now()->addMinutes(5)->toDateTimeString());
        $this->givenStripePaymentFor($order);
        $this->occurrenceRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->seatedOrderCompletionGuard->shouldReceive('assertSeatsHeld')->once()->andReturnUsing(function () {
            $this->refundLog->steps[] = 'seat check';

            throw new ResourceConflictException('gone');
        });

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
        }

        $this->assertSame(['begin', 'seat check', 'end', 'begin', 'refund', 'end'], $this->refundLog->steps);
    }

    public function test_a_second_payment_for_an_order_already_paid_by_another_payment_is_refunded_quietly(): void
    {
        $order = $this->stripePaidOrder();
        $this->givenStripePaymentFor($order);
        $this->stripePaymentsRepository->shouldReceive('findWhere')->with(['order_id' => 1])->andReturn(collect([
            (new StripePaymentDomainObject)->setOrderId(1)->setPaymentIntentId('pi_first')->setChargeId('ch_first'),
            (new StripePaymentDomainObject)->setOrderId(1)->setPaymentIntentId('pi_test'),
        ]));
        $this->orderRepository->shouldNotReceive('updateFromArray');

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
        }

        $this->assertSame([1], $this->refundLog->orderIds);
        $this->assertSame([false], $this->refundLog->notifyBuyer);
        $this->assertSame(['re_test'], $this->refundLog->recordedRefundIds);
    }

    public function test_a_repeated_webhook_for_the_payment_that_paid_the_order_is_ignored(): void
    {
        $order = $this->stripePaidOrder();
        $stripePayment = (new StripePaymentDomainObject)
            ->setOrderId(1)
            ->setPaymentIntentId('pi_test')
            ->setChargeId('ch_test')
            ->setOrder($order);
        $this->stripePaymentsRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')->andReturn($stripePayment);
        $this->stripePaymentsRepository->shouldNotReceive('findWhere');
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test', 'amount_received' => 1000, 'currency' => 'eur']));

        $this->assertSame([], $this->refundLog->orderIds);
    }

    private function stripePaidOrder(): OrderDomainObject
    {
        return (new OrderDomainObject)->setShortId('o_test')
            ->setId(1)
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setPaymentStatus(OrderPaymentStatus::PAYMENT_RECEIVED->name)
            ->setPaymentProvider(PaymentProviders::STRIPE->value);
    }

    private function expiredOrderAwaitingPayment(): OrderDomainObject
    {
        $order = (new OrderDomainObject)->setShortId('o_test')
            ->setId(1)
            ->setEventId(9)
            ->setStatus(OrderStatus::RESERVED->name)
            ->setPaymentStatus(OrderPaymentStatus::AWAITING_PAYMENT->name)
            ->setReservedUntil(now()->subMinutes(2)->toDateTimeString());
        $order->setOrderItems(collect([(new OrderItemDomainObject)->setEventOccurrenceId(5)]));

        return $order;
    }

    private function offlinePaidOrder(): OrderDomainObject
    {
        return (new OrderDomainObject)->setShortId('o_test')
            ->setId(1)
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setPaymentStatus(OrderPaymentStatus::PAYMENT_RECEIVED->name)
            ->setPaymentProvider(PaymentProviders::OFFLINE->value);
    }

    private function givenStripePaymentFor(OrderDomainObject $order): void
    {
        $stripePayment = (new StripePaymentDomainObject)
            ->setOrderId(1)
            ->setPaymentIntentId('pi_test')
            ->setOrder($order);

        $this->stripePaymentsRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')->andReturn($stripePayment);
    }

    private function assertLatePaymentRefundedAndRejected(string $orderStatus): void
    {
        $order = (new OrderDomainObject)->setShortId('o_test')
            ->setId(1)
            ->setStatus($orderStatus)
            ->setPaymentStatus(OrderPaymentStatus::AWAITING_PAYMENT->name);

        $stripePayment = (new StripePaymentDomainObject)
            ->setOrderId(1)
            ->setPaymentIntentId('pi_test')
            ->setOrder($order);

        $this->stripePaymentsRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')->andReturn($stripePayment);

        $this->orderRepository->shouldNotReceive('loadRelation');
        $this->orderRepository->shouldNotReceive('updateFromArray');

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
        }

        $this->assertSame([1], $this->refundLog->orderIds, 'The late payment should have been refunded exactly once');
        $this->assertSame(['re_test'], $this->refundLog->recordedRefundIds);
        $this->assertSame([false], $this->refundLog->recordedInsideTransaction, 'The refund row must be written after the rolled-back transaction or it is lost');
    }

    public function test_a_payment_handled_by_a_concurrent_request_while_waiting_for_the_lock_is_not_refunded_twice(): void
    {
        $this->cache->shouldReceive('has')->with('payment_intent_handled_pi_test')->andReturn(false, true);
        $this->givenStripePaymentFor($this->expiredOrderAwaitingPayment());
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));

        $this->assertSame([], $this->refundLog->orderIds);
    }

    public function test_payment_without_an_order_is_rejected_without_a_refund(): void
    {
        $stripePayment = (new StripePaymentDomainObject)
            ->setOrderId(1)
            ->setPaymentIntentId('pi_test');

        $this->stripePaymentsRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')->andReturn($stripePayment);

        $this->expectException(CannotAcceptPaymentException::class);

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
        } finally {
            $this->assertSame([], $this->refundLog->orderIds);
        }
    }
}
