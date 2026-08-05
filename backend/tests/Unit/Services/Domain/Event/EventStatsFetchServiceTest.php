<?php

namespace Tests\Unit\Services\Domain\Event;

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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
