<?php

namespace Tests\Unit\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\EventDailyStatisticDomainObject;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsRefundService;
use HiEvents\Values\MoneyValue;
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
        $this->logger = Mockery::mock(LoggerInterface::class);

        $this->service = new EventStatisticsRefundService(
            $this->eventStatisticsRepository,
            $this->eventDailyStatisticRepository,
            $this->logger
        );
    }

    public function testUpdateForRefundFullAmount(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';
        $currency = 'USD';

        // Create mock order
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getCurrency')->andReturn($currency);
        $order->shouldReceive('getTotalGross')->andReturn(100.00);
        $order->shouldReceive('getTotalTax')->andReturn(8.00);
        $order->shouldReceive('getTotalFee')->andReturn(2.00);

        // Create refund amount (full refund)
        $refundAmount = MoneyValue::fromFloat(100.00, $currency);

        // Mock aggregate event statistics
        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getSalesTotalGross')->andReturn(1000.00);
        $eventStatistics->shouldReceive('getTotalRefunded')->andReturn(50.00);
        $eventStatistics->shouldReceive('getTotalTax')->andReturn(80.00);
        $eventStatistics->shouldReceive('getTotalFee')->andReturn(20.00);

        // Mock daily event statistics
        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getSalesTotalGross')->andReturn(500.00);
        $eventDailyStatistic->shouldReceive('getTotalRefunded')->andReturn(25.00);
        $eventDailyStatistic->shouldReceive('getTotalTax')->andReturn(40.00);
        $eventDailyStatistic->shouldReceive('getTotalFee')->andReturn(10.00);

        // Expect finding aggregate statistics
        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        // Expect updating aggregate statistics (full refund = 100% proportion)
        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'sales_total_gross' => 900.00,     // 1000 - 100
                    'total_refunded' => 150.00,        // 50 + 100
                    'total_tax' => 72.00,              // 80 - 8 (100% of order tax)
                    'total_fee' => 18.00,              // 20 - 2 (100% of order fee)
                ],
                ['event_id' => $eventId]
            )
            ->once();

        // Expect finding daily statistics
        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturn($eventDailyStatistic);

        // Expect updating daily statistics
        $this->eventDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'sales_total_gross' => 400.00,     // 500 - 100
                    'total_refunded' => 125.00,        // 25 + 100
                    'total_tax' => 32.00,              // 40 - 8
                    'total_fee' => 8.00,               // 10 - 2
                ],
                [
                    'event_id' => $eventId,
                    'date' => '2024-01-15',
                ]
            )
            ->once();

        // Expect logging
        $this->logger->shouldReceive('info')->twice();

        // Execute
        $this->service->updateForRefund($order, $refundAmount);


        $this->assertTrue(true);
    }

    public function testUpdateForRefundPartialAmount(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';
        $currency = 'USD';

        // Create mock order
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getCurrency')->andReturn($currency);
        $order->shouldReceive('getTotalGross')->andReturn(100.00);
        $order->shouldReceive('getTotalTax')->andReturn(8.00);
        $order->shouldReceive('getTotalFee')->andReturn(2.00);

        // Create refund amount (50% partial refund)
        $refundAmount = MoneyValue::fromFloat(50.00, $currency);

        // Mock aggregate event statistics
        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getSalesTotalGross')->andReturn(1000.00);
        $eventStatistics->shouldReceive('getTotalRefunded')->andReturn(50.00);
        $eventStatistics->shouldReceive('getTotalTax')->andReturn(80.00);
        $eventStatistics->shouldReceive('getTotalFee')->andReturn(20.00);

        // Mock daily event statistics
        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getSalesTotalGross')->andReturn(500.00);
        $eventDailyStatistic->shouldReceive('getTotalRefunded')->andReturn(25.00);
        $eventDailyStatistic->shouldReceive('getTotalTax')->andReturn(40.00);
        $eventDailyStatistic->shouldReceive('getTotalFee')->andReturn(10.00);

        // Expect finding aggregate statistics
        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        // Expect updating aggregate statistics (50% refund = 0.5 proportion)
        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'sales_total_gross' => 950.00,     // 1000 - 50
                    'total_refunded' => 100.00,        // 50 + 50
                    'total_tax' => 76.00,              // 80 - 4 (50% of order tax)
                    'total_fee' => 19.00,              // 20 - 1 (50% of order fee)
                ],
                ['event_id' => $eventId]
            )
            ->once();

        // Expect finding daily statistics
        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturn($eventDailyStatistic);

        // Expect updating daily statistics
        $this->eventDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'sales_total_gross' => 450.00,     // 500 - 50
                    'total_refunded' => 75.00,         // 25 + 50
                    'total_tax' => 36.00,              // 40 - 4
                    'total_fee' => 9.00,               // 10 - 1
                ],
                [
                    'event_id' => $eventId,
                    'date' => '2024-01-15',
                ]
            )
            ->once();

        // Expect logging
        $this->logger->shouldReceive('info')->twice();

        // Execute
        $this->service->updateForRefund($order, $refundAmount);


        $this->assertTrue(true);
    }

    public function testThrowsExceptionWhenAggregateStatisticsNotFound(): void
    {
        $eventId = 1;
        $orderId = 123;
        $currency = 'USD';

        // Create mock order
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCurrency')->andReturn($currency);

        // Create refund amount
        $refundAmount = MoneyValue::fromFloat(50.00, $currency);

        // Expect aggregate statistics not found
        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturnNull();

        // Expect exception
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage("Event statistics not found for event {$eventId}");

        // Execute
        $this->service->updateForRefund($order, $refundAmount);


        $this->assertTrue(true);
    }

    public function testLogsWarningWhenDailyStatisticsNotFound(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';
        $currency = 'USD';

        // Create mock order
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getCurrency')->andReturn($currency);
        $order->shouldReceive('getTotalGross')->andReturn(100.00);
        $order->shouldReceive('getTotalTax')->andReturn(8.00);
        $order->shouldReceive('getTotalFee')->andReturn(2.00);

        // Create refund amount
        $refundAmount = MoneyValue::fromFloat(50.00, $currency);

        // Mock aggregate event statistics
        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getSalesTotalGross')->andReturn(1000.00);
        $eventStatistics->shouldReceive('getTotalRefunded')->andReturn(50.00);
        $eventStatistics->shouldReceive('getTotalTax')->andReturn(80.00);
        $eventStatistics->shouldReceive('getTotalFee')->andReturn(20.00);

        // Expect finding aggregate statistics
        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        // Expect updating aggregate statistics
        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->once();

        // Expect daily statistics not found
        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturnNull();

        // Expect warning log for missing daily statistics
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

        // Expect info log for aggregate update
        $this->logger->shouldReceive('info')->once();

        // Should not attempt to update daily statistics
        $this->eventDailyStatisticRepository->shouldNotReceive('updateWhere');

        // Execute
        $this->service->updateForRefund($order, $refundAmount);


        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
