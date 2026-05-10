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
use Illuminate\Database\Query\Expression;
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
    private MockInterface|EventOccurrenceStatisticRepositoryInterface $eventOccurrenceStatisticRepository;
    private MockInterface|EventOccurrenceDailyStatisticRepositoryInterface $eventOccurrenceDailyStatisticRepository;
    private MockInterface|OrderRepositoryInterface $orderRepository;
    private MockInterface|LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventStatisticsRepository = Mockery::mock(EventStatisticRepositoryInterface::class);
        $this->eventDailyStatisticRepository = Mockery::mock(EventDailyStatisticRepositoryInterface::class);
        $this->eventOccurrenceStatisticRepository = Mockery::mock(EventOccurrenceStatisticRepositoryInterface::class);
        $this->eventOccurrenceDailyStatisticRepository = Mockery::mock(EventOccurrenceDailyStatisticRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);

        // Default: the order reload (eager-loading items for the occurrence path) returns
        // an order with no occurrence items so the occurrence pass is skipped. Tests that
        // exercise the occurrence path override this expectation.
        $this->stubOrderReload(totalGross: 0.0, items: []);

        $this->service = new EventStatisticsRefundService(
            $this->eventStatisticsRepository,
            $this->eventDailyStatisticRepository,
            $this->eventOccurrenceStatisticRepository,
            $this->eventOccurrenceDailyStatisticRepository,
            $this->orderRepository,
            $this->logger
        );
    }

    /**
     * Helper that stubs `orderRepository->loadRelation(...)->findById(...)` to return
     * an OrderDomainObject pre-stocked with the given items + totalGross + createdAt.
     *
     * @param OrderItemDomainObject[] $items
     */
    private function stubOrderReload(
        float $totalGross,
        array $items,
        string $createdAt = '2026-04-10 09:00:00',
    ): MockInterface {
        $reloaded = Mockery::mock(OrderDomainObject::class);
        $reloaded->shouldReceive('getOrderItems')->andReturn(new Collection($items));
        $reloaded->shouldReceive('getTotalGross')->andReturn($totalGross);
        $reloaded->shouldReceive('getCreatedAt')->andReturn($createdAt);

        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('findById')->andReturn($reloaded);

        return $reloaded;
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

        // Default setUp stubs the order reload to return totalGross=0 with no items, so
        // the occurrence pass must be skipped entirely. Assert that — nothing here exercises
        // the new B4 / B5 code paths.
        $this->eventOccurrenceStatisticRepository->shouldNotReceive('updateWhere');
        $this->eventOccurrenceDailyStatisticRepository->shouldNotReceive('updateWhere');

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

        $this->eventOccurrenceStatisticRepository->shouldNotReceive('updateWhere');
        $this->eventOccurrenceDailyStatisticRepository->shouldNotReceive('updateWhere');

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

    /**
     * The order is loaded with order_items so the occurrence pass can run. Verify that:
     *   1. The order reload happens exactly ONCE (perf fix — used to load twice).
     *   2. updateWhere fires on both occurrence stats and occurrence daily stats.
     *   3. The deltas are emitted as DB::raw atomic increments (not scalars).
     *   4. The version column is bumped via raw SQL so optimistic readers see the change.
     */
    public function testUpdateForRefundUpdatesOccurrenceStatsForOrderWithItems(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';
        $currency = 'USD';

        $order = $this->makeBaseOrderMock($eventId, $orderId, $orderDate, totalGross: 100.00);
        $refundAmount = MoneyValue::fromFloat(100.00, $currency);

        // Order reload returns one item on occurrence 50.
        $item = $this->makeOrderItemMock(
            occurrenceId: 50,
            totalGross: 100.00,
            totalTax: 8.00,
            totalServiceFee: 2.00,
        );

        // Override the default no-items reload — and assert it happens exactly once
        // across the whole flow (regression guard for the perf duplication fix).
        $reloaded = Mockery::mock(OrderDomainObject::class);
        $reloaded->shouldReceive('getOrderItems')->andReturn(new Collection([$item]));
        $reloaded->shouldReceive('getTotalGross')->andReturn(100.00);
        $reloaded->shouldReceive('getCreatedAt')->andReturn($orderDate);

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('findById')->with($orderId)->once()->andReturn($reloaded);

        $this->service = new EventStatisticsRefundService(
            $this->eventStatisticsRepository,
            $this->eventDailyStatisticRepository,
            $this->eventOccurrenceStatisticRepository,
            $this->eventOccurrenceDailyStatisticRepository,
            $this->orderRepository,
            $this->logger
        );

        $this->stubAggregateAndDailyPaths($eventId);

        $this->eventOccurrenceStatisticRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn(array $attrs) =>
                    $this->isRawIncrement($attrs['sales_total_gross'] ?? null, 'sales_total_gross', '-')
                    && $this->isRawIncrement($attrs['total_refunded'] ?? null, 'total_refunded', '+')
                    && $this->isRawIncrement($attrs['total_tax'] ?? null, 'total_tax', '-')
                    && $this->isRawIncrement($attrs['total_fee'] ?? null, 'total_fee', '-')
                    && $this->isVersionBump($attrs['version'] ?? null)
                ),
                ['event_occurrence_id' => 50]
            );

        $this->eventOccurrenceDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn(array $attrs) =>
                    $this->isRawIncrement($attrs['sales_total_gross'] ?? null, 'sales_total_gross', '-')
                    && $this->isRawIncrement($attrs['total_refunded'] ?? null, 'total_refunded', '+')
                    && $this->isVersionBump($attrs['version'] ?? null)
                ),
                ['event_occurrence_id' => 50, 'date' => '2024-01-15']
            );

        $this->logger->shouldReceive('info')->twice();

        $this->service->updateForRefund($order, $refundAmount);

        $this->assertTrue(true);
    }

    /**
     * An order with items split across two different occurrences must produce one
     * updateWhere call per occurrence on each stats repository (4 calls total).
     */
    public function testUpdateForRefundSplitsRefundAcrossMultipleOccurrences(): void
    {
        $eventId = 1;
        $orderId = 200;
        $orderDate = '2024-02-20 14:00:00';
        $currency = 'USD';

        $order = $this->makeBaseOrderMock($eventId, $orderId, $orderDate, totalGross: 200.00);
        $refundAmount = MoneyValue::fromFloat(200.00, $currency);

        // 60% of the order belongs to occurrence 100, 40% to occurrence 200.
        $itemA = $this->makeOrderItemMock(occurrenceId: 100, totalGross: 120.00, totalTax: 10.00, totalServiceFee: 2.00);
        $itemB = $this->makeOrderItemMock(occurrenceId: 200, totalGross: 80.00, totalTax: 6.00, totalServiceFee: 2.00);

        $reloaded = Mockery::mock(OrderDomainObject::class);
        $reloaded->shouldReceive('getOrderItems')->andReturn(new Collection([$itemA, $itemB]));
        $reloaded->shouldReceive('getTotalGross')->andReturn(200.00);
        $reloaded->shouldReceive('getCreatedAt')->andReturn($orderDate);

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('findById')->with($orderId)->once()->andReturn($reloaded);

        $this->service = new EventStatisticsRefundService(
            $this->eventStatisticsRepository,
            $this->eventDailyStatisticRepository,
            $this->eventOccurrenceStatisticRepository,
            $this->eventOccurrenceDailyStatisticRepository,
            $this->orderRepository,
            $this->logger
        );

        $this->stubAggregateAndDailyPaths($eventId);

        // Expect one updateWhere per occurrence on each occurrence-stats repo. Order
        // shouldn't matter (PHP foreach over the items map preserves insertion order).
        $this->eventOccurrenceStatisticRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(Mockery::any(), ['event_occurrence_id' => 100]);

        $this->eventOccurrenceStatisticRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(Mockery::any(), ['event_occurrence_id' => 200]);

        $this->eventOccurrenceDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(Mockery::any(), ['event_occurrence_id' => 100, 'date' => '2024-02-20']);

        $this->eventOccurrenceDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(Mockery::any(), ['event_occurrence_id' => 200, 'date' => '2024-02-20']);

        $this->logger->shouldReceive('info')->twice();

        $this->service->updateForRefund($order, $refundAmount);

        $this->assertTrue(true);
    }

    /**
     * Order items without an event_occurrence_id (legacy / non-recurring orders) must
     * not trigger any occurrence-stats updates.
     */
    public function testUpdateForRefundSkipsOccurrencePathWhenNoItemsHaveOccurrenceId(): void
    {
        $eventId = 1;
        $orderId = 300;
        $orderDate = '2024-03-10 12:00:00';
        $currency = 'USD';

        $order = $this->makeBaseOrderMock($eventId, $orderId, $orderDate, totalGross: 50.00);
        $refundAmount = MoneyValue::fromFloat(50.00, $currency);

        $itemWithoutOccurrence = $this->makeOrderItemMock(
            occurrenceId: null,
            totalGross: 50.00,
            totalTax: 4.00,
            totalServiceFee: 1.00,
        );

        $reloaded = Mockery::mock(OrderDomainObject::class);
        $reloaded->shouldReceive('getOrderItems')->andReturn(new Collection([$itemWithoutOccurrence]));
        $reloaded->shouldReceive('getTotalGross')->andReturn(50.00);
        $reloaded->shouldReceive('getCreatedAt')->andReturn($orderDate);

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('findById')->with($orderId)->once()->andReturn($reloaded);

        $this->service = new EventStatisticsRefundService(
            $this->eventStatisticsRepository,
            $this->eventDailyStatisticRepository,
            $this->eventOccurrenceStatisticRepository,
            $this->eventOccurrenceDailyStatisticRepository,
            $this->orderRepository,
            $this->logger
        );

        $this->stubAggregateAndDailyPaths($eventId);

        $this->eventOccurrenceStatisticRepository->shouldNotReceive('updateWhere');
        $this->eventOccurrenceDailyStatisticRepository->shouldNotReceive('updateWhere');

        $this->logger->shouldReceive('info')->twice();

        $this->service->updateForRefund($order, $refundAmount);

        $this->assertTrue(true);
    }

    /**
     * Stubs the aggregate + daily stats lookups + updateWhere calls so the test can
     * focus on the occurrence path. Returns nothing — sets up Mockery expectations.
     */
    private function stubAggregateAndDailyPaths(int $eventId): void
    {
        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getSalesTotalGross')->andReturn(1000.00);
        $eventStatistics->shouldReceive('getTotalRefunded')->andReturn(0.0);
        $eventStatistics->shouldReceive('getTotalTax')->andReturn(80.00);
        $eventStatistics->shouldReceive('getTotalFee')->andReturn(20.00);

        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getSalesTotalGross')->andReturn(500.00);
        $eventDailyStatistic->shouldReceive('getTotalRefunded')->andReturn(0.0);
        $eventDailyStatistic->shouldReceive('getTotalTax')->andReturn(40.00);
        $eventDailyStatistic->shouldReceive('getTotalFee')->andReturn(10.00);

        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);
        $this->eventStatisticsRepository->shouldReceive('updateWhere')->once();

        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($eventDailyStatistic);
        $this->eventDailyStatisticRepository->shouldReceive('updateWhere')->once();
    }

    private function makeBaseOrderMock(int $eventId, int $orderId, string $orderDate, float $totalGross): MockInterface
    {
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getCurrency')->andReturn('USD');
        $order->shouldReceive('getTotalGross')->andReturn($totalGross);
        $order->shouldReceive('getTotalTax')->andReturn(0.0);
        $order->shouldReceive('getTotalFee')->andReturn(0.0);
        return $order;
    }

    private function makeOrderItemMock(?int $occurrenceId, float $totalGross, float $totalTax, float $totalServiceFee): MockInterface
    {
        $item = Mockery::mock(OrderItemDomainObject::class);
        $item->shouldReceive('getEventOccurrenceId')->andReturn($occurrenceId);
        $item->shouldReceive('getTotalGross')->andReturn($totalGross);
        $item->shouldReceive('getTotalTax')->andReturn($totalTax);
        $item->shouldReceive('getTotalServiceFee')->andReturn($totalServiceFee);
        return $item;
    }

    private function isRawIncrement(mixed $value, string $column, string $op): bool
    {
        if (!$value instanceof Expression) {
            return false;
        }
        $sql = (string) $value->getValue(\DB::connection()->getQueryGrammar());
        return str_contains($sql, $column) && str_contains($sql, $op);
    }

    private function isVersionBump(mixed $value): bool
    {
        if (!$value instanceof Expression) {
            return false;
        }
        $sql = (string) $value->getValue(\DB::connection()->getQueryGrammar());
        return $sql === 'version + 1';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
