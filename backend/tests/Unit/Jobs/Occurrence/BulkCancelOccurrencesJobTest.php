<?php

namespace Tests\Unit\Jobs\Occurrence;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Jobs\Occurrence\BulkCancelOccurrencesJob;
use HiEvents\Jobs\Occurrence\RefundOccurrenceOrdersJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class BulkCancelOccurrencesJobTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|Mockery\MockInterface $occurrenceRepository;
    private EventRepositoryInterface|Mockery\MockInterface $eventRepository;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        DB::shouldReceive('transaction')->andReturnUsing(fn($callback) => $callback());

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
    }

    public function testHandleCancelsMultipleOccurrences(): void
    {
        Log::shouldReceive('info')->once();

        $occ1 = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ1->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occ1->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $occ2 = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ2->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occ2->shouldReceive('getStartDate')->andReturn('2026-06-22 10:00:00');

        $this->occurrenceRepository->shouldReceive('findFirstWhere')
            ->with([EventOccurrenceDomainObjectAbstract::ID => 10, EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn($occ1);
        $this->occurrenceRepository->shouldReceive('findFirstWhere')
            ->with([EventOccurrenceDomainObjectAbstract::ID => 20, EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn($occ2);

        $this->occurrenceRepository->shouldReceive('updateWhere')->twice();

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getRecurrenceRule')->andReturn(['frequency' => 'weekly', 'excluded_dates' => []]);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(1, Mockery::on(fn($attrs) => count($attrs[EventDomainObjectAbstract::RECURRENCE_RULE]['excluded_dates']) === 2));

        $job = new BulkCancelOccurrencesJob(1, [10, 20]);
        $job->handle($this->occurrenceRepository, $this->eventRepository);

        Event::assertDispatchedTimes(OccurrenceCancelledEvent::class, 2);
    }

    public function testHandleSkipsAlreadyCancelledOccurrences(): void
    {
        Log::shouldReceive('info')->once();

        $occ = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::CANCELLED->name);

        $this->occurrenceRepository->shouldReceive('findFirstWhere')
            ->with([EventOccurrenceDomainObjectAbstract::ID => 10, EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn($occ);
        $this->occurrenceRepository->shouldNotReceive('updateWhere');

        $job = new BulkCancelOccurrencesJob(1, [10]);
        $job->handle($this->occurrenceRepository, $this->eventRepository);

        Event::assertNotDispatched(OccurrenceCancelledEvent::class);
    }

    public function test_it_skips_occurrences_not_belonging_to_event(): void
    {
        Log::shouldReceive('info')->once();

        $this->occurrenceRepository->shouldReceive('findFirstWhere')
            ->with([EventOccurrenceDomainObjectAbstract::ID => 10, EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(null);

        $this->occurrenceRepository->shouldNotReceive('updateWhere');

        $job = new BulkCancelOccurrencesJob(1, [10]);
        $job->handle($this->occurrenceRepository, $this->eventRepository);

        Event::assertNotDispatched(OccurrenceCancelledEvent::class);
    }

    public function testHandleDispatchesRefundJobWhenFlagIsTrue(): void
    {
        Bus::fake();
        Log::shouldReceive('info')->once();

        $occ = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occ->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $this->occurrenceRepository->shouldReceive('findFirstWhere')
            ->with([EventOccurrenceDomainObjectAbstract::ID => 10, EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn($occ);
        $this->occurrenceRepository->shouldReceive('updateWhere')->once();

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::SINGLE->name);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);

        $job = new BulkCancelOccurrencesJob(1, [10], refundOrders: true);
        $job->handle($this->occurrenceRepository, $this->eventRepository);

        Bus::assertDispatched(RefundOccurrenceOrdersJob::class, fn($j) => $j->eventId === 1 && $j->occurrenceId === 10);
    }

    public function testHandleDoesNotDispatchRefundJobWhenFlagIsFalse(): void
    {
        Bus::fake();
        Log::shouldReceive('info')->once();

        $occ = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occ->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $this->occurrenceRepository->shouldReceive('findFirstWhere')
            ->with([EventOccurrenceDomainObjectAbstract::ID => 10, EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn($occ);
        $this->occurrenceRepository->shouldReceive('updateWhere')->once();

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::SINGLE->name);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);

        $job = new BulkCancelOccurrencesJob(1, [10], refundOrders: false);
        $job->handle($this->occurrenceRepository, $this->eventRepository);

        Bus::assertNotDispatched(RefundOccurrenceOrdersJob::class);
    }

    public function testHandleDoesNotAddExcludedDatesForSingleEvent(): void
    {
        Log::shouldReceive('info')->once();

        $occ = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occ->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $this->occurrenceRepository->shouldReceive('findFirstWhere')
            ->with([EventOccurrenceDomainObjectAbstract::ID => 10, EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn($occ);
        $this->occurrenceRepository->shouldReceive('updateWhere')->once();

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::SINGLE->name);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository->shouldNotReceive('updateFromArray');

        $job = new BulkCancelOccurrencesJob(1, [10]);
        $job->handle($this->occurrenceRepository, $this->eventRepository);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
