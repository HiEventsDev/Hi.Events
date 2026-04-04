<?php

namespace Tests\Unit\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\EventDailyStatisticDomainObject;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsRefundService;
use HiEvents\Values\MoneyValue;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

class EventStatisticsRefundServiceTest extends TestCase
{
    private EventStatisticsRefundService $service;
    private MockInterface|EventStatisticRepositoryInterface $eventStatisticsRepository;
    private MockInterface|EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository;
    private MockInterface|LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventStatisticsRepository = Mockery::mock(EventStatisticRepositoryInterface::class);
        $this->eventDailyStatisticRepository = Mockery::mock(EventDailyStatisticRepositoryInterface::class);
        $eventOccurrenceStatisticRepository = Mockery::mock(EventOccurrenceStatisticRepositoryInterface::class);
        $eventOccurrenceDailyStatisticRepository = Mockery::mock(EventOccurrenceDailyStatisticRepositoryInterface::class);
        $orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);

        // Mock the order repository to return an order with no occurrence items (non-recurring)
        $mockOrder = Mockery::mock(OrderDomainObject::class);
        $mockOrder->shouldReceive('getOrderItems')->andReturn(new Collection());
        $mockOrder->shouldReceive('getTotalGross')->andReturn(0.0);
        $orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $orderRepository->shouldReceive('findById')->andReturn($mockOrder);

        $this->service = new EventStatisticsRefundService(
            $this->eventStatisticsRepository,
            $this->eventDailyStatisticRepository,
            $eventOccurrenceStatisticRepository,
            $eventOccurrenceDailyStatisticRepository,
            $orderRepository,
            $this->logger
        );
    }

    public function testUpdateForRefundFullAmount(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';
        $currency = 'USD';

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getCurrency')->andReturn($currency);
        $order->shouldReceive('getTotalGross')->andReturn(100.00);
        $order->shouldReceive('getTotalTax')->andReturn(8.00);
        $order->shouldReceive('getTotalFee')->andReturn(2.00);

        $refundAmount = MoneyValue::fromFloat(100.00, $currency);

        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getSalesTotalGross')->andReturn(1000.00);
        $eventStatistics->shouldReceive('getTotalRefunded')->andReturn(50.00);
        $eventStatistics->shouldReceive('getTotalTax')->andReturn(80.00);
        $eventStatistics->shouldReceive('getTotalFee')->andReturn(20.00);

        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getSalesTotalGross')->andReturn(500.00);
        $eventDailyStatistic->shouldReceive('getTotalRefunded')->andReturn(25.00);
        $eventDailyStatistic->shouldReceive('getTotalTax')->andReturn(40.00);
        $eventDailyStatistic->shouldReceive('getTotalFee')->andReturn(10.00);

        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'sales_total_gross' => 900.00,
                    'total_refunded' => 150.00,
                    'total_tax' => 72.00,
                    'total_fee' => 18.00,
                ],
                ['event_id' => $eventId]
            )
            ->once();

        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturn($eventDailyStatistic);

        $this->eventDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'sales_total_gross' => 400.00,
                    'total_refunded' => 125.00,
                    'total_tax' => 32.00,
                    'total_fee' => 8.00,
                ],
                [
                    'event_id' => $eventId,
                    'date' => '2024-01-15',
                ]
            )
            ->once();

        $this->logger->shouldReceive('info')->twice();

        $this->service->updateForRefund($order, $refundAmount);

        $this->assertTrue(true);
    }

    public function testUpdateForRefundPartialAmount(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';
        $currency = 'USD';

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getCurrency')->andReturn($currency);
        $order->shouldReceive('getTotalGross')->andReturn(100.00);
        $order->shouldReceive('getTotalTax')->andReturn(8.00);
        $order->shouldReceive('getTotalFee')->andReturn(2.00);

        $refundAmount = MoneyValue::fromFloat(50.00, $currency);

        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getSalesTotalGross')->andReturn(1000.00);
        $eventStatistics->shouldReceive('getTotalRefunded')->andReturn(50.00);
        $eventStatistics->shouldReceive('getTotalTax')->andReturn(80.00);
        $eventStatistics->shouldReceive('getTotalFee')->andReturn(20.00);

        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getSalesTotalGross')->andReturn(500.00);
        $eventDailyStatistic->shouldReceive('getTotalRefunded')->andReturn(25.00);
        $eventDailyStatistic->shouldReceive('getTotalTax')->andReturn(40.00);
        $eventDailyStatistic->shouldReceive('getTotalFee')->andReturn(10.00);

        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'sales_total_gross' => 950.00,
                    'total_refunded' => 100.00,
                    'total_tax' => 76.00,
                    'total_fee' => 19.00,
                ],
                ['event_id' => $eventId]
            )
            ->once();

        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturn($eventDailyStatistic);

        $this->eventDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'sales_total_gross' => 450.00,
                    'total_refunded' => 75.00,
                    'total_tax' => 36.00,
                    'total_fee' => 9.00,
                ],
                [
                    'event_id' => $eventId,
                    'date' => '2024-01-15',
                ]
            )
            ->once();

        $this->logger->shouldReceive('info')->twice();

        $this->service->updateForRefund($order, $refundAmount);

        $this->assertTrue(true);
    }

    public function testThrowsExceptionWhenAggregateStatisticsNotFound(): void
    {
        $eventId = 1;
        $orderId = 123;
        $currency = 'USD';

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCurrency')->andReturn($currency);

        $refundAmount = MoneyValue::fromFloat(50.00, $currency);

        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage("Event statistics not found for event {$eventId}");

        $this->service->updateForRefund($order, $refundAmount);
    }

    public function testLogsWarningWhenDailyStatisticsNotFound(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';
        $currency = 'USD';

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getCurrency')->andReturn($currency);
        $order->shouldReceive('getTotalGross')->andReturn(100.00);
        $order->shouldReceive('getTotalTax')->andReturn(8.00);
        $order->shouldReceive('getTotalFee')->andReturn(2.00);

        $refundAmount = MoneyValue::fromFloat(50.00, $currency);

        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getSalesTotalGross')->andReturn(1000.00);
        $eventStatistics->shouldReceive('getTotalRefunded')->andReturn(50.00);
        $eventStatistics->shouldReceive('getTotalTax')->andReturn(80.00);
        $eventStatistics->shouldReceive('getTotalFee')->andReturn(20.00);

        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->once();

        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturnNull();

        $this->logger
            ->shouldReceive('warning')
            ->with(
                'Event daily statistics not found for refund',
                [
                    'event_id' => $eventId,
                    'date' => '2024-01-15',
                    'order_id' => $orderId,
                ]
            )
            ->once();

        $this->logger->shouldReceive('info')->once();

        $this->eventDailyStatisticRepository->shouldNotReceive('updateWhere');

        $this->service->updateForRefund($order, $refundAmount);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
