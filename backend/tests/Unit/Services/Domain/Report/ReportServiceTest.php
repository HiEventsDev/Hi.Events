<?php

namespace Tests\Unit\Services\Domain\Report;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Report\Reports\DailySalesReport;
use HiEvents\Services\Domain\Report\Reports\OccurrenceSummaryReport;
use HiEvents\Services\Domain\Report\Reports\ProductSalesReport;
use HiEvents\Services\Domain\Report\Reports\PromoCodesReport;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    private CacheRepository|Mockery\MockInterface $cache;
    private DatabaseManager|Mockery\MockInterface $queryBuilder;
    private EventRepositoryInterface|Mockery\MockInterface $eventRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = Mockery::mock(CacheRepository::class);
        $this->queryBuilder = Mockery::mock(DatabaseManager::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('UTC');

        $this->eventRepository->shouldReceive('findById')->with(1)->andReturn($event);
    }

    private function setupCachePassthrough(): void
    {
        $this->cache->shouldReceive('remember')
            ->andReturnUsing(fn($key, $ttl, $callback) => $callback());
    }

    public function testProductSalesReportGeneratesWithoutOccurrence(): void
    {
        $this->setupCachePassthrough();
        $this->queryBuilder->shouldReceive('select')
            ->once()
            ->with(Mockery::on(fn($sql) => str_contains($sql, 'filtered_orders') && !str_contains($sql, ':occurrence_id')), ['event_id' => 1])
            ->andReturn([]);

        $report = new ProductSalesReport($this->cache, $this->queryBuilder, $this->eventRepository);
        $result = $report->generateReport(1, Carbon::now()->subDays(30), Carbon::now());

        $this->assertCount(0, $result);
    }

    public function testProductSalesReportGeneratesWithOccurrence(): void
    {
        $this->setupCachePassthrough();
        $this->queryBuilder->shouldReceive('select')
            ->once()
            ->with(
                Mockery::on(fn($sql) => str_contains($sql, ':occurrence_id')),
                ['event_id' => 1, 'occurrence_id' => 10],
            )
            ->andReturn([]);

        $report = new ProductSalesReport($this->cache, $this->queryBuilder, $this->eventRepository);
        $result = $report->generateReport(1, Carbon::now()->subDays(30), Carbon::now(), occurrenceId: 10);

        $this->assertCount(0, $result);
    }

    public function testDailySalesReportUsesEventDailyStatsWithoutOccurrence(): void
    {
        $this->setupCachePassthrough();
        $this->queryBuilder->shouldReceive('select')
            ->once()
            ->with(
                Mockery::on(fn($sql) => str_contains($sql, 'event_daily_statistics') && !str_contains($sql, 'event_occurrence_daily_statistics')),
                ['event_id' => 1],
            )
            ->andReturn([]);

        $report = new DailySalesReport($this->cache, $this->queryBuilder, $this->eventRepository);
        $result = $report->generateReport(1, Carbon::now()->subDays(7), Carbon::now());

        $this->assertCount(0, $result);
    }

    public function testDailySalesReportUsesOccurrenceDailyStatsWithOccurrence(): void
    {
        $this->setupCachePassthrough();
        $this->queryBuilder->shouldReceive('select')
            ->once()
            ->with(
                Mockery::on(fn($sql) => str_contains($sql, 'event_occurrence_daily_statistics') && str_contains($sql, ':occurrence_id')),
                ['event_id' => 1, 'occurrence_id' => 10],
            )
            ->andReturn([]);

        $report = new DailySalesReport($this->cache, $this->queryBuilder, $this->eventRepository);
        $result = $report->generateReport(1, Carbon::now()->subDays(7), Carbon::now(), occurrenceId: 10);

        $this->assertCount(0, $result);
    }

    public function testPromoCodesReportGeneratesWithOccurrence(): void
    {
        $this->setupCachePassthrough();
        $this->queryBuilder->shouldReceive('select')
            ->once()
            ->with(
                Mockery::on(fn($sql) => str_contains($sql, ':occurrence_id')),
                ['event_id' => 1, 'occurrence_id' => 10],
            )
            ->andReturn([]);

        $report = new PromoCodesReport($this->cache, $this->queryBuilder, $this->eventRepository);
        $result = $report->generateReport(1, Carbon::now()->subDays(30), Carbon::now(), occurrenceId: 10);

        $this->assertCount(0, $result);
    }

    public function testPromoCodesReportGeneratesWithoutOccurrence(): void
    {
        $this->setupCachePassthrough();
        $this->queryBuilder->shouldReceive('select')
            ->once()
            ->with(Mockery::on(fn($sql) => !str_contains($sql, ':occurrence_id')), ['event_id' => 1])
            ->andReturn([]);

        $report = new PromoCodesReport($this->cache, $this->queryBuilder, $this->eventRepository);
        $result = $report->generateReport(1, Carbon::now()->subDays(30), Carbon::now());

        $this->assertCount(0, $result);
    }

    public function testOccurrenceSummaryReportGenerates(): void
    {
        $this->setupCachePassthrough();
        $this->queryBuilder->shouldReceive('select')
            ->once()
            ->with(
                Mockery::on(fn($sql) => str_contains($sql, 'event_occurrences') && str_contains($sql, 'event_occurrence_statistics')),
                Mockery::on(fn($bindings) => $bindings['event_id'] === 1
                    && isset($bindings['start_date'])
                    && isset($bindings['end_date'])),
            )
            ->andReturn([
                (object) ['occurrence_id' => 1, 'products_sold' => 5, 'total_gross' => 100],
            ]);

        $report = new OccurrenceSummaryReport($this->cache, $this->queryBuilder, $this->eventRepository);
        $result = $report->generateReport(1);

        $this->assertCount(1, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
