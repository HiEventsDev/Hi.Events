<?php

namespace Tests\Unit\Jobs\Occurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Jobs\Occurrence\BulkCancelOccurrencesJob;
use HiEvents\Jobs\Occurrence\SendOccurrenceCancellationEmailJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Domain\Event\RecurrenceRuleExclusionService;
use HiEvents\Services\Domain\EventOccurrence\CancelOccurrenceAttendeesService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class BulkCancelOccurrencesJobTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|Mockery\MockInterface $occurrenceRepository;

    private RecurrenceRuleExclusionService|Mockery\MockInterface $exclusionService;

    private CancelOccurrenceAttendeesService|Mockery\MockInterface $cancelAttendeesService;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Bus::fake();

        DB::shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->exclusionService = Mockery::mock(RecurrenceRuleExclusionService::class);
        $this->cancelAttendeesService = Mockery::mock(CancelOccurrenceAttendeesService::class);
        $this->cancelAttendeesService->shouldReceive('cancelForOccurrence')->andReturn([])->byDefault();
        $this->exclusionService->shouldReceive('addExclusions')->byDefault();
    }

    public function test_handle_cancels_multiple_occurrences(): void
    {
        Log::shouldReceive('info')->once();

        $occ1 = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ1->shouldReceive('getEventId')->andReturn(1);
        $occ1->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occ1->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $occ2 = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ2->shouldReceive('getEventId')->andReturn(1);
        $occ2->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occ2->shouldReceive('getStartDate')->andReturn('2026-06-22 10:00:00');

        $this->occurrenceRepository->shouldReceive('findByIdLocked')
            ->with(10)
            ->once()
            ->andReturn($occ1);
        $this->occurrenceRepository->shouldReceive('findByIdLocked')
            ->with(20)
            ->once()
            ->andReturn($occ2);

        $this->occurrenceRepository->shouldReceive('updateWhere')->times(4);

        $this->exclusionService
            ->shouldReceive('addExclusions')
            ->once()
            ->with(1, ['2026-06-15 10:00:00']);
        $this->exclusionService
            ->shouldReceive('addExclusions')
            ->once()
            ->with(1, ['2026-06-22 10:00:00']);

        $this->cancelAttendeesService->shouldReceive('cancelForOccurrence')->with(1, 10)->andReturn([101, 102]);

        $job = new BulkCancelOccurrencesJob(1, [10, 20]);
        $job->handle($this->occurrenceRepository, $this->exclusionService, $this->cancelAttendeesService);

        Event::assertDispatchedTimes(OccurrenceCancelledEvent::class, 2);

        Bus::assertDispatchedTimes(SendOccurrenceCancellationEmailJob::class, 1);
        Bus::assertDispatched(SendOccurrenceCancellationEmailJob::class, function (SendOccurrenceCancellationEmailJob $emailJob) {
            return $emailJob->eventId === 1
                && $emailJob->occurrenceId === 10
                && $emailJob->attendeeIds === [101, 102];
        });
    }

    public function test_handle_skips_already_cancelled_occurrences(): void
    {
        Log::shouldReceive('info')->once();

        $occ = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ->shouldReceive('getEventId')->andReturn(1);
        $occ->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::CANCELLED->name);

        $this->occurrenceRepository->shouldReceive('findByIdLocked')
            ->with(10)
            ->once()
            ->andReturn($occ);
        $this->occurrenceRepository->shouldNotReceive('updateWhere');
        $this->exclusionService->shouldNotReceive('addExclusions');

        $job = new BulkCancelOccurrencesJob(1, [10]);
        $job->handle($this->occurrenceRepository, $this->exclusionService, $this->cancelAttendeesService);

        Event::assertNotDispatched(OccurrenceCancelledEvent::class);
    }

    public function test_retry_recovers_cancelled_occurrence_with_pending_side_effects(): void
    {
        Log::shouldReceive('info')->once();

        $occ = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ->shouldReceive('getEventId')->andReturn(1);
        $occ->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::CANCELLED->name);
        $occ->shouldReceive('getCancelledAttendeesCount')->andReturn(null);
        $occ->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $this->occurrenceRepository->shouldReceive('findByIdLocked')
            ->with(10)
            ->once()
            ->andReturn($occ);

        $attendeeQuery = Mockery::mock();
        $attendeeQuery->shouldReceive('where')->andReturnSelf();
        $attendeeQuery->shouldReceive('whereNull')->andReturnSelf();
        $attendeeQuery->shouldReceive('pluck')->with('id')->andReturn(collect([101, 102]));
        DB::shouldReceive('table')->with('attendees')->andReturn($attendeeQuery);

        $this->exclusionService
            ->shouldReceive('addExclusions')
            ->once()
            ->with(1, ['2026-06-15 10:00:00']);

        $this->occurrenceRepository->shouldReceive('updateWhere')
            ->once()
            ->with(['cancelled_attendees_count' => 2], ['id' => 10]);

        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(2);

        $job = new BulkCancelOccurrencesJob(1, [10], refundOrders: true);
        $job->setJob($queueJob);
        $job->handle($this->occurrenceRepository, $this->exclusionService, $this->cancelAttendeesService);

        Event::assertDispatched(OccurrenceCancelledEvent::class, fn ($e) => $e->occurrenceId === 10 && $e->refundOrders === true);
        Bus::assertDispatched(SendOccurrenceCancellationEmailJob::class, function (SendOccurrenceCancellationEmailJob $emailJob) {
            return $emailJob->occurrenceId === 10 && $emailJob->attendeeIds === [101, 102];
        });
    }

    public function test_retry_skips_cancelled_occurrence_with_dispatched_side_effects(): void
    {
        Log::shouldReceive('info')->once();

        $occ = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ->shouldReceive('getEventId')->andReturn(1);
        $occ->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::CANCELLED->name);
        $occ->shouldReceive('getCancelledAttendeesCount')->andReturn(5);

        $this->occurrenceRepository->shouldReceive('findByIdLocked')
            ->with(10)
            ->once()
            ->andReturn($occ);
        $this->occurrenceRepository->shouldNotReceive('updateWhere');
        $this->exclusionService->shouldNotReceive('addExclusions');

        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(2);

        $job = new BulkCancelOccurrencesJob(1, [10]);
        $job->setJob($queueJob);
        $job->handle($this->occurrenceRepository, $this->exclusionService, $this->cancelAttendeesService);

        Event::assertNotDispatched(OccurrenceCancelledEvent::class);
        Bus::assertNotDispatched(SendOccurrenceCancellationEmailJob::class);
    }

    public function test_it_skips_occurrences_not_belonging_to_event(): void
    {
        Log::shouldReceive('info')->once();

        $foreignOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $foreignOccurrence->shouldReceive('getEventId')->andReturn(99);

        $this->occurrenceRepository->shouldReceive('findByIdLocked')
            ->with(10)
            ->once()
            ->andReturn($foreignOccurrence);

        $this->occurrenceRepository->shouldNotReceive('updateWhere');
        $this->exclusionService->shouldNotReceive('addExclusions');

        $job = new BulkCancelOccurrencesJob(1, [10]);
        $job->handle($this->occurrenceRepository, $this->exclusionService, $this->cancelAttendeesService);

        Event::assertNotDispatched(OccurrenceCancelledEvent::class);
    }

    public function test_handle_dispatches_event_with_refund_flag_true(): void
    {
        Log::shouldReceive('info')->once();

        $occ = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ->shouldReceive('getEventId')->andReturn(1);
        $occ->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occ->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $this->occurrenceRepository->shouldReceive('findByIdLocked')
            ->with(10)
            ->once()
            ->andReturn($occ);
        $this->occurrenceRepository->shouldReceive('updateWhere')->twice();

        $job = new BulkCancelOccurrencesJob(1, [10], refundOrders: true);
        $job->handle($this->occurrenceRepository, $this->exclusionService, $this->cancelAttendeesService);

        Event::assertDispatched(OccurrenceCancelledEvent::class, fn ($e) => $e->occurrenceId === 10 && $e->refundOrders === true);
    }

    public function test_handle_dispatches_event_with_refund_flag_false(): void
    {
        Log::shouldReceive('info')->once();

        $occ = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ->shouldReceive('getEventId')->andReturn(1);
        $occ->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occ->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $this->occurrenceRepository->shouldReceive('findByIdLocked')
            ->with(10)
            ->once()
            ->andReturn($occ);
        $this->occurrenceRepository->shouldReceive('updateWhere')->twice();

        $job = new BulkCancelOccurrencesJob(1, [10], refundOrders: false);
        $job->handle($this->occurrenceRepository, $this->exclusionService, $this->cancelAttendeesService);

        Event::assertDispatched(OccurrenceCancelledEvent::class, fn ($e) => $e->refundOrders === false);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
