<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderRefundDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Mail\Order\OrderRefunded;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsRefundService;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\ChargeRefundUpdatedHandler;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use HiEvents\Values\MoneyValue;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Log\Logger;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\Refund;
use Tests\TestCase;

class ChargeRefundUpdatedHandlerTest extends TestCase
{
    private const ORDER_ID = 500;

    private const ORDER_SHORT_ID = 'o_refund_test';

    private const EVENT_ID = 50;

    private const PAYMENT_INTENT_ID = 'pi_test_123';

    private const REFUND_ID = 're_test_456';

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private StripePaymentsRepositoryInterface|MockInterface $stripePaymentsRepository;

    private EventStatisticsRefundService|MockInterface $eventStatisticsRefundService;

    private OrderRefundRepositoryInterface|MockInterface $orderRefundRepository;

    private DomainEventDispatcherService|MockInterface $domainEventDispatcherService;

    private TransactionLockService|MockInterface $transactionLockService;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private Mailer|MockInterface $mailer;

    private ChargeRefundUpdatedHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->stripePaymentsRepository = Mockery::mock(StripePaymentsRepositoryInterface::class);
        $this->eventStatisticsRefundService = Mockery::mock(EventStatisticsRefundService::class);
        $this->orderRefundRepository = Mockery::mock(OrderRefundRepositoryInterface::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);
        $this->transactionLockService = Mockery::mock(TransactionLockService::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->mailer = Mockery::mock(Mailer::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->handler = new ChargeRefundUpdatedHandler(
            $this->orderRepository,
            $this->stripePaymentsRepository,
            Mockery::mock(Logger::class)->shouldIgnoreMissing(),
            $databaseManager,
            $this->eventStatisticsRefundService,
            $this->orderRefundRepository,
            $this->domainEventDispatcherService,
            $this->transactionLockService,
            $this->eventRepository,
            $this->mailer,
        );
    }

    public function test_a_partial_refund_records_the_amount_adjusts_statistics_and_marks_the_order_partially_refunded(): void
    {
        $this->givenStripePaymentForOrder();
        $this->givenTheOrderIsLocked();
        $this->givenNoExistingRefund();
        $this->givenOrder(totalGross: 100.0, totalRefunded: 0.0);

        $this->orderRepository->shouldReceive('increment')
            ->once()
            ->with(self::ORDER_ID, OrderDomainObjectAbstract::TOTAL_REFUNDED, 25.0);
        $this->orderRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(self::ORDER_ID, [OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::PARTIALLY_REFUNDED->name]);

        $this->eventStatisticsRefundService->shouldReceive('updateForRefund')
            ->once()
            ->withArgs(fn (OrderDomainObject $order, MoneyValue $amount): bool => $order->getId() === self::ORDER_ID
                && $amount->toMinorUnit() === 2500);

        $createdRefund = null;
        $this->orderRefundRepository->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $attributes) use (&$createdRefund) {
                $createdRefund = $attributes;

                return new OrderRefundDomainObject;
            });

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn (OrderEvent $event) => $event->type === DomainEventType::ORDER_REFUNDED
                && $event->orderId === self::ORDER_ID));

        $this->mailer->shouldNotReceive('to');

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: Refund::STATUS_SUCCEEDED));

        $this->assertSame(self::ORDER_ID, $createdRefund['order_id']);
        $this->assertSame(PaymentProviders::STRIPE->value, $createdRefund['payment_provider']);
        $this->assertSame(self::REFUND_ID, $createdRefund['refund_id']);
        $this->assertSame(25.0, $createdRefund['amount']);
        $this->assertSame('USD', $createdRefund['currency']);
        $this->assertSame(Refund::STATUS_SUCCEEDED, $createdRefund['status']);
        $this->assertSame(self::PAYMENT_INTENT_ID, $createdRefund['metadata']['payment_intent']);
    }

    public function test_a_refund_that_reaches_the_order_total_marks_the_order_fully_refunded(): void
    {
        $this->givenStripePaymentForOrder();
        $this->givenTheOrderIsLocked();
        $this->givenNoExistingRefund();
        $this->givenOrder(totalGross: 100.0, totalRefunded: 60.0);

        $this->orderRepository->shouldReceive('increment')->once()->with(self::ORDER_ID, OrderDomainObjectAbstract::TOTAL_REFUNDED, 40.0);
        $this->orderRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(self::ORDER_ID, [OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::REFUNDED->name]);
        $this->eventStatisticsRefundService->shouldReceive('updateForRefund')->once();
        $this->orderRefundRepository->shouldReceive('create')->once()->andReturn(new OrderRefundDomainObject);
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $this->handler->handleEvent($this->refund(amountMinor: 4000, status: Refund::STATUS_SUCCEEDED));
    }

    public function test_the_order_is_locked_before_the_refund_is_looked_up(): void
    {
        $calls = [];

        $this->givenStripePaymentForOrder();
        $this->orderRepository->shouldReceive('findById')->once()->with(self::ORDER_ID)->andReturn($this->order(100.0, 0.0));
        $this->transactionLockService->shouldReceive('lockOrder')
            ->once()
            ->with(self::ORDER_SHORT_ID)
            ->andReturnUsing(function () use (&$calls) {
                $calls[] = 'lock';
            });
        $this->orderRefundRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['refund_id' => self::REFUND_ID])
            ->andReturnUsing(function () use (&$calls) {
                $calls[] = 'find-refund';

                return (new OrderRefundDomainObject)->setId(1)->setRefundId(self::REFUND_ID);
            });

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: Refund::STATUS_SUCCEEDED));

        $this->assertSame(['lock', 'find-refund'], $calls);
    }

    public function test_a_replayed_refund_webhook_is_ignored_and_does_not_email_again(): void
    {
        $this->givenStripePaymentForOrder();
        $this->givenTheOrderIsLocked();
        $this->orderRefundRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['refund_id' => self::REFUND_ID])
            ->andReturn((new OrderRefundDomainObject)->setId(1)->setRefundId(self::REFUND_ID));
        $this->orderRepository->shouldReceive('findById')->once()->with(self::ORDER_ID)->andReturn($this->order(100.0, 0.0));

        $this->orderRepository->shouldNotReceive('increment');
        $this->orderRepository->shouldNotReceive('updateFromArray');
        $this->eventStatisticsRefundService->shouldNotReceive('updateForRefund');
        $this->orderRefundRepository->shouldNotReceive('create');
        $this->domainEventDispatcherService->shouldNotReceive('dispatch');
        $this->mailer->shouldNotReceive('to');

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: Refund::STATUS_SUCCEEDED, notifyBuyer: true));
    }

    public function test_a_refund_for_an_unknown_payment_intent_is_ignored(): void
    {
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['payment_intent_id' => self::PAYMENT_INTENT_ID])
            ->andReturnNull();

        $this->transactionLockService->shouldNotReceive('lockOrder');
        $this->orderRefundRepository->shouldNotReceive('findFirstWhere');
        $this->orderRepository->shouldNotReceive('findById');
        $this->eventStatisticsRefundService->shouldNotReceive('updateForRefund');

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: Refund::STATUS_SUCCEEDED));
    }

    public static function stillProcessingStatuses(): array
    {
        return [
            'pending' => [Refund::STATUS_PENDING, 'insufficient_funds'],
            'pending without a reason' => [Refund::STATUS_PENDING, null],
            'requires_action' => [Refund::STATUS_REQUIRES_ACTION, null],
        ];
    }

    #[DataProvider('stillProcessingStatuses')]
    public function test_a_refund_still_processing_leaves_the_order_untouched(string $status, ?string $pendingReason): void
    {
        $this->givenStripePaymentForOrder();
        $this->givenTheOrderIsLocked();
        $this->givenNoExistingRefund();
        $this->orderRepository->shouldReceive('findById')->once()->with(self::ORDER_ID)->andReturn($this->order(100.0, 0.0));

        $this->orderRepository->shouldNotReceive('updateFromArray');
        $this->orderRepository->shouldNotReceive('increment');
        $this->eventStatisticsRefundService->shouldNotReceive('updateForRefund');
        $this->orderRefundRepository->shouldNotReceive('create');
        $this->domainEventDispatcherService->shouldNotReceive('dispatch');
        $this->mailer->shouldNotReceive('to');

        $this->handler->handleEvent($this->refund(
            amountMinor: 2500,
            status: $status,
            notifyBuyer: true,
            pendingReason: $pendingReason,
        ));
    }

    public static function finishedFailureStatuses(): array
    {
        return [
            'failed' => [Refund::STATUS_FAILED],
            'canceled' => [Refund::STATUS_CANCELED],
        ];
    }

    #[DataProvider('finishedFailureStatuses')]
    public function test_a_finished_failure_marks_the_order_refund_failed_and_records_the_attempt(string $status): void
    {
        $this->givenStripePaymentForOrder();
        $this->givenTheOrderIsLocked();
        $this->givenNoExistingRefund();
        $this->givenOrder(totalGross: 100.0, totalRefunded: 0.0);

        $this->orderRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(self::ORDER_ID, [OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::REFUND_FAILED->name]);

        $createdRefund = null;
        $this->orderRefundRepository->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $attributes) use (&$createdRefund) {
                $createdRefund = $attributes;

                return new OrderRefundDomainObject;
            });

        $this->orderRepository->shouldNotReceive('increment');
        $this->eventStatisticsRefundService->shouldNotReceive('updateForRefund');
        $this->domainEventDispatcherService->shouldNotReceive('dispatch');
        $this->mailer->shouldNotReceive('to');

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: $status, notifyBuyer: true));

        $this->assertSame(self::REFUND_ID, $createdRefund['refund_id']);
        $this->assertSame($status, $createdRefund['status']);
        $this->assertSame(25.0, $createdRefund['amount']);
    }

    public function test_a_pending_refund_that_later_succeeds_is_recorded_once(): void
    {
        $recorded = null;

        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')
            ->times(3)
            ->with(['payment_intent_id' => self::PAYMENT_INTENT_ID])
            ->andReturn((new StripePaymentDomainObject)->setOrderId(self::ORDER_ID));
        $this->transactionLockService->shouldReceive('lockOrder')->times(3)->with(self::ORDER_SHORT_ID);
        $this->orderRepository->shouldReceive('findById')->with(self::ORDER_ID)->andReturn($this->order(100.0, 0.0));
        $this->orderRefundRepository->shouldReceive('findFirstWhere')
            ->times(3)
            ->with(['refund_id' => self::REFUND_ID])
            ->andReturnUsing(function () use (&$recorded) {
                return $recorded;
            });
        $this->orderRefundRepository->shouldReceive('create')
            ->once()
            ->andReturnUsing(function () use (&$recorded) {
                return $recorded = (new OrderRefundDomainObject)->setId(1)->setRefundId(self::REFUND_ID);
            });

        $this->orderRepository->shouldReceive('increment')->once()->with(self::ORDER_ID, OrderDomainObjectAbstract::TOTAL_REFUNDED, 25.0);
        $this->orderRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(self::ORDER_ID, [OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::PARTIALLY_REFUNDED->name]);
        $this->eventStatisticsRefundService->shouldReceive('updateForRefund')->once();
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();
        $this->givenTheBuyerIsEmailed();

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: Refund::STATUS_PENDING, notifyBuyer: true));
        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: Refund::STATUS_SUCCEEDED, notifyBuyer: true));
        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: Refund::STATUS_SUCCEEDED, notifyBuyer: true));
    }

    public function test_a_succeeded_refund_requested_with_notify_buyer_emails_the_buyer_the_refunded_amount(): void
    {
        $this->givenASuccessfulRefundIsRecorded(email: 'buyer@example.com', locale: 'fr');

        $sentMail = $this->givenTheBuyerIsEmailed(email: 'buyer@example.com', locale: 'fr');

        $this->handler->handleEvent($this->refund(amountMinor: 2550, status: Refund::STATUS_SUCCEEDED, notifyBuyer: true));

        $this->assertSame(2550, $sentMail->mail->content()->with['refundAmount']->toMinorUnit());
        $this->assertSame('USD', $sentMail->mail->content()->with['refundAmount']->getMoney()->getCurrency()->getCurrencyCode());
    }

    public function test_a_succeeded_refund_without_the_notify_flag_does_not_email_the_buyer(): void
    {
        $this->givenASuccessfulRefundIsRecorded(email: 'buyer@example.com', locale: 'en');

        $this->eventRepository->shouldNotReceive('findById');
        $this->mailer->shouldNotReceive('to');

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: Refund::STATUS_SUCCEEDED));
    }

    public function test_a_succeeded_refund_for_an_order_without_an_email_does_not_email_anyone(): void
    {
        $this->givenASuccessfulRefundIsRecorded(email: null, locale: 'en');

        $this->eventRepository->shouldNotReceive('findById');
        $this->mailer->shouldNotReceive('to');

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: Refund::STATUS_SUCCEEDED, notifyBuyer: true));
    }

    private function refund(int $amountMinor, string $status, bool $notifyBuyer = false, ?string $pendingReason = null): Refund
    {
        return Refund::constructFrom([
            'id' => self::REFUND_ID,
            'object' => 'refund',
            'amount' => $amountMinor,
            'currency' => 'usd',
            'payment_intent' => self::PAYMENT_INTENT_ID,
            'status' => $status,
            'pending_reason' => $pendingReason,
            'metadata' => $notifyBuyer ? [StripePaymentIntentRefundService::NOTIFY_BUYER_METADATA_KEY => '1'] : [],
        ]);
    }

    private function givenStripePaymentForOrder(): void
    {
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['payment_intent_id' => self::PAYMENT_INTENT_ID])
            ->andReturn((new StripePaymentDomainObject)->setOrderId(self::ORDER_ID));
    }

    private function givenTheOrderIsLocked(): void
    {
        $this->transactionLockService->shouldReceive('lockOrder')->once()->with(self::ORDER_SHORT_ID);
    }

    private function givenNoExistingRefund(): void
    {
        $this->orderRefundRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['refund_id' => self::REFUND_ID])
            ->andReturnNull();
    }

    private function givenOrder(float $totalGross, float $totalRefunded, ?string $email = null, string $locale = 'en'): void
    {
        $this->orderRepository->shouldReceive('findById')
            ->twice()
            ->with(self::ORDER_ID)
            ->andReturn($this->order($totalGross, $totalRefunded, $email, $locale));
    }

    private function order(float $totalGross, float $totalRefunded, ?string $email = 'buyer@example.com', string $locale = 'en'): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setShortId(self::ORDER_SHORT_ID)
            ->setEventId(self::EVENT_ID)
            ->setCurrency('USD')
            ->setEmail($email)
            ->setLocale($locale)
            ->setTotalGross($totalGross)
            ->setTotalRefunded($totalRefunded);
    }

    private function givenASuccessfulRefundIsRecorded(?string $email, string $locale): void
    {
        $this->givenStripePaymentForOrder();
        $this->givenTheOrderIsLocked();
        $this->givenNoExistingRefund();
        $this->givenOrder(totalGross: 100.0, totalRefunded: 0.0, email: $email, locale: $locale);

        $this->orderRepository->shouldReceive('increment')->once();
        $this->orderRepository->shouldReceive('updateFromArray')->once();
        $this->eventStatisticsRefundService->shouldReceive('updateForRefund')->once();
        $this->orderRefundRepository->shouldReceive('create')->once()->andReturn(new OrderRefundDomainObject);
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();
    }

    private function givenTheBuyerIsEmailed(string $email = 'buyer@example.com', string $locale = 'en'): object
    {
        $sent = new class
        {
            public ?OrderRefunded $mail = null;
        };

        $event = (new EventDomainObject)
            ->setId(self::EVENT_ID)
            ->setOrganizer(new OrganizerDomainObject)
            ->setEventSettings(new EventSettingDomainObject);

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->once()->with(self::EVENT_ID)->andReturn($event);

        $this->mailer->shouldReceive('to')->once()->with($email)->andReturnSelf();
        $this->mailer->shouldReceive('locale')->once()->with($locale)->andReturnSelf();
        $this->mailer->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (OrderRefunded $mail) use ($sent) {
                $sent->mail = $mail;

                return true;
            }));

        return $sent;
    }
}
