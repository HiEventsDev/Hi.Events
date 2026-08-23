<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderRefundDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsRefundService;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\ChargeRefundUpdatedHandler;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Values\MoneyValue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Log\Logger;
use Mockery;
use Mockery\MockInterface;
use Stripe\Refund;
use Tests\TestCase;

class ChargeRefundUpdatedHandlerTest extends TestCase
{
    private const ORDER_ID = 500;

    private const PAYMENT_INTENT_ID = 'pi_test_123';

    private const REFUND_ID = 're_test_456';

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private StripePaymentsRepositoryInterface|MockInterface $stripePaymentsRepository;

    private EventStatisticsRefundService|MockInterface $eventStatisticsRefundService;

    private OrderRefundRepositoryInterface|MockInterface $orderRefundRepository;

    private DomainEventDispatcherService|MockInterface $domainEventDispatcherService;

    private ChargeRefundUpdatedHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->stripePaymentsRepository = Mockery::mock(StripePaymentsRepositoryInterface::class);
        $this->eventStatisticsRefundService = Mockery::mock(EventStatisticsRefundService::class);
        $this->orderRefundRepository = Mockery::mock(OrderRefundRepositoryInterface::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);

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
        );
    }

    public function test_a_partial_refund_records_the_amount_adjusts_statistics_and_marks_the_order_partially_refunded(): void
    {
        $this->givenStripePaymentForOrder();
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

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: 'succeeded'));

        $this->assertSame(self::ORDER_ID, $createdRefund['order_id']);
        $this->assertSame(PaymentProviders::STRIPE->value, $createdRefund['payment_provider']);
        $this->assertSame(self::REFUND_ID, $createdRefund['refund_id']);
        $this->assertSame(25.0, $createdRefund['amount']);
        $this->assertSame('USD', $createdRefund['currency']);
        $this->assertSame('succeeded', $createdRefund['status']);
        $this->assertSame(self::PAYMENT_INTENT_ID, $createdRefund['metadata']['payment_intent']);
    }

    public function test_a_refund_that_reaches_the_order_total_marks_the_order_fully_refunded(): void
    {
        $this->givenStripePaymentForOrder();
        $this->givenNoExistingRefund();
        $this->givenOrder(totalGross: 100.0, totalRefunded: 60.0);

        $this->orderRepository->shouldReceive('increment')->once()->with(self::ORDER_ID, OrderDomainObjectAbstract::TOTAL_REFUNDED, 40.0);
        $this->orderRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(self::ORDER_ID, [OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::REFUNDED->name]);
        $this->eventStatisticsRefundService->shouldReceive('updateForRefund')->once();
        $this->orderRefundRepository->shouldReceive('create')->once()->andReturn(new OrderRefundDomainObject);
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $this->handler->handleEvent($this->refund(amountMinor: 4000, status: 'succeeded'));
    }

    public function test_a_replayed_refund_webhook_is_ignored(): void
    {
        $this->givenStripePaymentForOrder();
        $this->orderRefundRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['refund_id' => self::REFUND_ID])
            ->andReturn((new OrderRefundDomainObject)->setId(1)->setRefundId(self::REFUND_ID));

        $this->orderRepository->shouldNotReceive('findById');
        $this->orderRepository->shouldNotReceive('increment');
        $this->orderRepository->shouldNotReceive('updateFromArray');
        $this->eventStatisticsRefundService->shouldNotReceive('updateForRefund');
        $this->orderRefundRepository->shouldNotReceive('create');
        $this->domainEventDispatcherService->shouldNotReceive('dispatch');

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: 'succeeded'));
    }

    public function test_a_refund_for_an_unknown_payment_intent_is_ignored(): void
    {
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['payment_intent_id' => self::PAYMENT_INTENT_ID])
            ->andReturnNull();

        $this->orderRefundRepository->shouldNotReceive('findFirstWhere');
        $this->orderRepository->shouldNotReceive('findById');
        $this->eventStatisticsRefundService->shouldNotReceive('updateForRefund');

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: 'succeeded'));
    }

    public function test_a_failed_refund_marks_the_order_refund_failed_without_touching_statistics(): void
    {
        $this->givenStripePaymentForOrder();
        $this->givenNoExistingRefund();
        $this->givenOrder(totalGross: 100.0, totalRefunded: 0.0);

        $this->orderRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(self::ORDER_ID, [OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::REFUND_FAILED->name]);

        $this->orderRepository->shouldNotReceive('increment');
        $this->eventStatisticsRefundService->shouldNotReceive('updateForRefund');
        $this->orderRefundRepository->shouldNotReceive('create');
        $this->domainEventDispatcherService->shouldNotReceive('dispatch');

        $this->handler->handleEvent($this->refund(amountMinor: 2500, status: 'failed'));
    }

    private function refund(int $amountMinor, string $status): Refund
    {
        return Refund::constructFrom([
            'id' => self::REFUND_ID,
            'object' => 'refund',
            'amount' => $amountMinor,
            'currency' => 'usd',
            'payment_intent' => self::PAYMENT_INTENT_ID,
            'status' => $status,
            'metadata' => [],
        ]);
    }

    private function givenStripePaymentForOrder(): void
    {
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['payment_intent_id' => self::PAYMENT_INTENT_ID])
            ->andReturn((new StripePaymentDomainObject)->setOrderId(self::ORDER_ID));
    }

    private function givenNoExistingRefund(): void
    {
        $this->orderRefundRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['refund_id' => self::REFUND_ID])
            ->andReturnNull();
    }

    private function givenOrder(float $totalGross, float $totalRefunded): void
    {
        $this->orderRepository->shouldReceive('findById')
            ->once()
            ->with(self::ORDER_ID)
            ->andReturn((new OrderDomainObject)
                ->setId(self::ORDER_ID)
                ->setCurrency('USD')
                ->setTotalGross($totalGross)
                ->setTotalRefunded($totalRefunded));
    }
}
