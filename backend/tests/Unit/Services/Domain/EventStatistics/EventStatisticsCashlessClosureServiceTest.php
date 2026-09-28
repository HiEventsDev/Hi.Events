<?php

namespace Tests\Unit\Services\Domain\EventStatistics;

use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCashlessClosureService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EventStatisticsCashlessClosureServiceTest extends TestCase
{
    private EventStatisticRepositoryInterface|MockInterface $eventRepository;

    private EventDailyStatisticRepositoryInterface|MockInterface $dailyRepository;

    private EventOccurrenceStatisticRepositoryInterface|MockInterface $occurrenceRepository;

    private EventOccurrenceDailyStatisticRepositoryInterface|MockInterface $occurrenceDailyRepository;

    private EventStatisticsCashlessClosureService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventStatisticRepositoryInterface::class);
        $this->dailyRepository = Mockery::mock(EventDailyStatisticRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceStatisticRepositoryInterface::class);
        $this->occurrenceDailyRepository = Mockery::mock(EventOccurrenceDailyStatisticRepositoryInterface::class);

        $this->service = new EventStatisticsCashlessClosureService(
            $this->eventRepository,
            $this->dailyRepository,
            $this->occurrenceRepository,
            $this->occurrenceDailyRepository,
        );
    }

    public function test_it_adds_the_amount_to_the_gross_and_before_additions_on_every_level_and_bumps_the_version(): void
    {
        $expectedColumns = ['sales_total_gross' => 15.5, 'sales_total_before_additions' => 15.5, 'version' => 1];

        $this->eventRepository->shouldReceive('incrementEach')
            ->once()->with($expectedColumns, [], ['event_id' => 7])->andReturn(1);
        $this->dailyRepository->shouldReceive('incrementEach')
            ->once()->with($expectedColumns, [], ['event_id' => 7, 'date' => '2026-09-28'])->andReturn(1);
        $this->occurrenceRepository->shouldReceive('incrementEach')
            ->once()->with($expectedColumns, [], ['event_id' => 7, 'event_occurrence_id' => 3])->andReturn(1);
        $this->occurrenceDailyRepository->shouldReceive('incrementEach')
            ->once()->with($expectedColumns, [], ['event_id' => 7, 'event_occurrence_id' => 3, 'date' => '2026-09-28'])->andReturn(1);

        $this->service->recordClosedBalance(eventId: 7, occurrenceId: 3, date: '2026-09-28', amount: 15.5);

        $this->addToAssertionCount(1);
    }

    public function test_it_creates_the_rows_that_do_not_exist_yet_without_touching_order_counters(): void
    {
        $this->eventRepository->shouldReceive('incrementEach')->andReturn(0);
        $this->dailyRepository->shouldReceive('incrementEach')->andReturn(0);
        $this->occurrenceRepository->shouldReceive('incrementEach')->andReturn(1);
        $this->occurrenceDailyRepository->shouldReceive('incrementEach')->andReturn(1);

        $this->eventRepository->shouldReceive('create')->once()->with([
            'event_id' => 7,
            'sales_total_gross' => 15.5,
            'sales_total_before_additions' => 15.5,
        ]);
        $this->dailyRepository->shouldReceive('create')->once()->with([
            'event_id' => 7,
            'date' => '2026-09-28',
            'sales_total_gross' => 15.5,
            'sales_total_before_additions' => 15.5,
        ]);

        $this->service->recordClosedBalance(eventId: 7, occurrenceId: 3, date: '2026-09-28', amount: 15.5);

        $this->addToAssertionCount(1);
    }

    public function test_it_skips_the_occurrence_tables_without_an_occurrence(): void
    {
        $this->eventRepository->shouldReceive('incrementEach')->andReturn(1);
        $this->dailyRepository->shouldReceive('incrementEach')->andReturn(1);
        $this->occurrenceRepository->shouldNotReceive('incrementEach');
        $this->occurrenceDailyRepository->shouldNotReceive('incrementEach');

        $this->service->recordClosedBalance(eventId: 7, occurrenceId: null, date: '2026-09-28', amount: 15.5);

        $this->addToAssertionCount(1);
    }
}
