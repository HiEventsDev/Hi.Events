<?php

namespace Tests\Unit\Services\Domain\Event;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\EventStatsRequestDTO;
use HiEvents\Services\Domain\Event\EventStatsFetchService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EventStatsFetchServiceTest extends TestCase
{
    private DatabaseManager|MockInterface $db;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private EventStatsFetchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Mockery::mock(DatabaseManager::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->service = new EventStatsFetchService($this->db, $this->eventRepository);
    }

    public function test_occurrence_scoped_stats_bind_event_id_with_occurrence_id(): void
    {
        $this->db
            ->shouldReceive('selectOne')
            ->once()
            ->with(
                Mockery::on(static fn (string $sql): bool => str_contains($sql, 'eods.event_occurrence_id = :occurrenceId')
                    && str_contains($sql, 'eods.event_id = :eventId')),
                [
                    'occurrenceId' => 200,
                    'eventId' => 10,
                    'startDate' => '2026-01-01',
                    'endDate' => '2026-01-31',
                ],
            )
            ->andReturn((object) [
                'total_products_sold' => 0,
                'total_orders' => 0,
                'total_gross_sales' => 0,
                'total_tax' => 0,
                'total_fees' => 0,
                'total_views' => 0,
                'total_refunded' => 0,
                'attendees_registered' => 0,
            ]);

        $this->db
            ->shouldReceive('select')
            ->once()
            ->with(
                Mockery::on(static fn (string $sql): bool => str_contains($sql, 'eods.event_occurrence_id = :occurrenceId')
                    && str_contains($sql, 'eods.event_id = :eventId')),
                [
                    'startDate' => '2026-01-01',
                    'endDate' => '2026-01-31',
                    'occurrenceId' => 200,
                    'eventId' => 10,
                ],
            )
            ->andReturn([]);

        $response = $this->service->getEventStats(new EventStatsRequestDTO(
            event_id: 10,
            start_date: '2026-01-01',
            end_date: '2026-01-31',
            occurrence_id: 200,
        ));

        $this->assertSame(0, $response->total_orders);
    }

    public function test_event_range_includes_occurrence_dates(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getStartDate')->andReturn('2090-02-18 17:00:00');
        $event->shouldReceive('getEndDate')->andReturn('2090-02-20 02:00:00');
        $this->eventRepository->shouldReceive('loadRelation')->once()->with(Mockery::on(static fn ($relationship): bool => $relationship instanceof Relationship && $relationship->getDomainObject() === EventOccurrenceDomainObject::class))->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->once()->with(10)->andReturn($event);
        $this->db->shouldReceive('selectOne')->once()->andReturn((object) [
            'min_date' => '2026-11-14', 'max_date' => '2026-11-14',
        ]);

        $range = (new \ReflectionMethod(EventStatsFetchService::class, 'resolveStatsDateRange'))
            ->invoke($this->service, 10, 'event');

        $this->assertSame(['2026-11-14 00:00:00', '2090-02-20 02:00:00'], $range);
    }

    public function test_daily_breakdown_stops_at_today_while_totals_cover_the_whole_range(): void
    {
        Carbon::setTestNow('2026-11-24 10:00:00');

        $this->db
            ->shouldReceive('selectOne')
            ->once()
            ->with(Mockery::type('string'), Mockery::on(static fn (array $bindings): bool => $bindings['endDate'] === '2090-02-20 02:00:00'))
            ->andReturn((object) [
                'total_products_sold' => 0,
                'total_orders' => 0,
                'total_gross_sales' => 0,
                'total_tax' => 0,
                'total_fees' => 0,
                'total_views' => 0,
                'total_refunded' => 0,
                'attendees_registered' => 0,
            ]);

        $this->db
            ->shouldReceive('select')
            ->once()
            ->with(Mockery::type('string'), [
                'startDate' => '2026-11-01 00:00:00',
                'endDate' => '2026-11-24',
                'eventId' => 10,
            ])
            ->andReturn([]);

        $response = $this->service->getEventStats(new EventStatsRequestDTO(
            event_id: 10,
            start_date: '2026-11-01 00:00:00',
            end_date: '2090-02-20 02:00:00',
        ));

        $this->assertSame('2090-02-20 02:00:00', $response->end_date);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }
}
