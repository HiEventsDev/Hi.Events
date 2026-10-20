<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsRefundService;
use HiEvents\Services\Domain\Order\OfflineOrderRefundService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Values\MoneyValue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OfflineOrderRefundServiceTest extends TestCase
{
    private const ORDER_ID = 900;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private OrderRefundRepositoryInterface|MockInterface $orderRefundRepository;

    private EventStatisticsRefundService|MockInterface $eventStatisticsRefundService;

    private DomainEventDispatcherService|MockInterface $domainEventDispatcherService;

    private OfflineOrderRefundService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->orderRefundRepository = Mockery::mock(OrderRefundRepositoryInterface::class);
        $this->eventStatisticsRefundService = Mockery::mock(EventStatisticsRefundService::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);

        $this->service = new OfflineOrderRefundService(
            $this->orderRepository,
            $this->orderRefundRepository,
            $this->eventStatisticsRefundService,
            $this->domainEventDispatcherService,
        );
    }

    public function test_a_full_refund_records_the_refund_and_marks_the_order_refunded(): void
    {
        $order = $this->givenOrder(totalGross: 100.0, totalRefunded: 0.0);

        $this->orderRefundRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $attributes): bool => $attributes['order_id'] === self::ORDER_ID
                && $attributes['payment_provider'] === PaymentProviders::OFFLINE->value
                && str_starts_with($attributes['refund_id'], 'offline_')
                && $attributes['amount'] === 100.0
                && $attributes['currency'] === 'USD'
                && $attributes['status'] === 'succeeded');

        $this->orderRepository->shouldReceive('increment')
            ->once()
            ->with(self::ORDER_ID, OrderDomainObjectAbstract::TOTAL_REFUNDED, 100.0);

        $this->orderRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(self::ORDER_ID, [OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::REFUNDED->name]);

        $this->eventStatisticsRefundService->shouldReceive('updateForRefund')
            ->once()
            ->withArgs(fn (OrderDomainObject $statsOrder, MoneyValue $amount): bool => $statsOrder->getId() === self::ORDER_ID
                && $amount->toMinorUnit() === 10000);

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn (OrderEvent $event): bool => $event->type === DomainEventType::ORDER_REFUNDED
                && $event->orderId === self::ORDER_ID);

        $this->service->refundOrder($order, MoneyValue::fromFloat(100.0, 'USD'));
    }

    public function test_a_partial_refund_marks_the_order_partially_refunded(): void
    {
        $order = $this->givenOrder(totalGross: 100.0, totalRefunded: 0.0);

        $this->orderRefundRepository->shouldReceive('create')->once();
        $this->orderRepository->shouldReceive('increment')
            ->once()
            ->with(self::ORDER_ID, OrderDomainObjectAbstract::TOTAL_REFUNDED, 40.0);

        $this->orderRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(self::ORDER_ID, [OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::PARTIALLY_REFUNDED->name]);

        $this->eventStatisticsRefundService->shouldReceive('updateForRefund')->once();
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $this->service->refundOrder($order, MoneyValue::fromFloat(40.0, 'USD'));
    }

    public function test_a_refund_completing_previous_partial_refunds_marks_the_order_refunded(): void
    {
        $order = $this->givenOrder(totalGross: 100.0, totalRefunded: 60.0);

        $this->orderRefundRepository->shouldReceive('create')->once();
        $this->orderRepository->shouldReceive('increment')
            ->once()
            ->with(self::ORDER_ID, OrderDomainObjectAbstract::TOTAL_REFUNDED, 40.0);

        $this->orderRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(self::ORDER_ID, [OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::REFUNDED->name]);

        $this->eventStatisticsRefundService->shouldReceive('updateForRefund')->once();
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $this->service->refundOrder($order, MoneyValue::fromFloat(40.0, 'USD'));
    }

    private function givenOrder(float $totalGross, float $totalRefunded): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setCurrency('USD')
            ->setTotalGross($totalGross)
            ->setTotalRefunded($totalRefunded);
    }
}
