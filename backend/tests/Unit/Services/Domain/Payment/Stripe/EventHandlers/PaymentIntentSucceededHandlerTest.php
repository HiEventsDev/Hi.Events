<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\CannotAcceptPaymentException;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OccurrenceStatusValidator;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use HiEvents\Services\Domain\Payment\Stripe\StripeRefundExpiredOrderService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Stripe\PaymentIntent;
use Tests\TestCase;

/**
 * StripeRefundExpiredOrderService is a readonly class Mockery cannot subclass,
 * so a readonly test double records the refund call.
 */
final readonly class RecordingRefundExpiredOrderService extends StripeRefundExpiredOrderService
{
    public function __construct(private RefundCallLog $log) {}

    public function refundExpiredOrder(PaymentIntent $paymentIntent, StripePaymentDomainObject $stripePayment, OrderDomainObject $order): void
    {
        $this->log->orderIds[] = (int) $order->getId();
    }
}

final class RefundCallLog
{
    /** @var list<int> */
    public array $orderIds = [];
}

class PaymentIntentSucceededHandlerTest extends TestCase
{
    private OrderRepositoryInterface|MockInterface $orderRepository;

    private StripePaymentsRepository|MockInterface $stripePaymentsRepository;

    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private RefundCallLog $refundLog;

    private PaymentIntentSucceededHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->stripePaymentsRepository = Mockery::mock(StripePaymentsRepository::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->refundLog = new RefundCallLog;

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $cache = Mockery::mock(Repository::class);
        $cache->shouldReceive('has')->andReturn(false);
        $cache->shouldReceive('put')->andReturnTrue();

        $this->handler = new PaymentIntentSucceededHandler(
            $this->orderRepository,
            $this->stripePaymentsRepository,
            Mockery::mock(AffiliateRepositoryInterface::class),
            Mockery::mock(ProductQuantityUpdateService::class),
            new RecordingRefundExpiredOrderService($this->refundLog),
            Mockery::mock(AttendeeRepositoryInterface::class),
            $databaseManager,
            Mockery::mock(LoggerInterface::class),
            $cache,
            Mockery::mock(DomainEventDispatcherService::class),
            Mockery::mock(OrderApplicationFeeService::class),
            Mockery::mock(EventSettingsRepositoryInterface::class),
            new OccurrenceStatusValidator($this->occurrenceRepository),
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
        $order = (new OrderDomainObject)
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
            // expected
        }

        $this->assertSame([], $this->refundLog->orderIds, 'An already-paid order must not be refunded on a duplicate webhook');
    }

    public function test_late_payment_on_a_cancelled_occurrence_is_refunded_and_rejected(): void
    {
        $orderItem = (new OrderItemDomainObject)->setEventOccurrenceId(5);

        $order = (new OrderDomainObject)
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

        // The order must not be completed on the reject path.
        $this->orderRepository->shouldNotReceive('updateFromArray');

        try {
            $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $this->fail('Expected CannotAcceptPaymentException was not thrown');
        } catch (CannotAcceptPaymentException) {
            // expected
        }

        $this->assertSame([1], $this->refundLog->orderIds, 'A late payment on a cancelled occurrence should be refunded exactly once');
    }

    private function assertLatePaymentRefundedAndRejected(string $orderStatus): void
    {
        $order = (new OrderDomainObject)
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
            // expected
        }

        $this->assertSame([1], $this->refundLog->orderIds, 'The late payment should have been refunded exactly once');
    }
}
