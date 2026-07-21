<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\Occurrence\SendOccurrenceCancellationEmailJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\CancelOccurrenceHandler;
use HiEvents\Services\Domain\Event\RecurrenceRuleExclusionService;
use HiEvents\Services\Domain\EventOccurrence\CancelOccurrenceAttendeesService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CancelOccurrenceHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private RecurrenceRuleExclusionService|MockInterface $exclusionService;

    private CancelOccurrenceAttendeesService|MockInterface $cancelAttendeesService;

    private DatabaseManager|MockInterface $databaseManager;

    private CancelOccurrenceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Bus::fake();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->exclusionService = Mockery::mock(RecurrenceRuleExclusionService::class);
        $this->cancelAttendeesService = Mockery::mock(CancelOccurrenceAttendeesService::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new CancelOccurrenceHandler(
            $this->occurrenceRepository,
            $this->exclusionService,
            $this->cancelAttendeesService,
            $this->databaseManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function expectAttendeeCancelCalled(int $eventId, int $occurrenceId, array $cancelledAttendeeIds = [], int $salesBackedCount = 0): void
    {
        $this->cancelAttendeesService
            ->shouldReceive('cancelForOccurrence')
            ->once()
            ->with($eventId, $occurrenceId)
            ->andReturn([
                'attendee_ids' => $cancelledAttendeeIds,
                'sales_backed_count' => $salesBackedCount,
            ]);
    }

    public function test_handle_sets_status_to_cancelled(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occurrence->shouldReceive('getEventId')->andReturn($eventId);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->with($occurrenceId)->andReturn($occurrence);
        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($occurrenceId, [
                EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::CANCELLED->name,
                EventOccurrenceDomainObjectAbstract::CANCELLED_ATTENDEES_COUNT => 5,
            ])
            ->andReturn($updatedOccurrence);
        $this->expectAttendeeCancelCalled($eventId, $occurrenceId, [101, 102], salesBackedCount: 5);
        $this->exclusionService
            ->shouldReceive('addExclusions')
            ->once()
            ->with($eventId, ['2026-06-15 10:00:00']);

        $result = $this->handler->handle($eventId, $occurrenceId);

        $this->assertSame($updatedOccurrence, $result);

        Event::assertDispatched(OccurrenceCancelledEvent::class, function ($e) use ($eventId, $occurrenceId) {
            return $e->eventId === $eventId
                && $e->occurrenceId === $occurrenceId;
        });

        Bus::assertDispatched(SendOccurrenceCancellationEmailJob::class, function (SendOccurrenceCancellationEmailJob $job) use ($eventId, $occurrenceId) {
            return $job->eventId === $eventId
                && $job->occurrenceId === $occurrenceId
                && $job->attendeeIds === [101, 102]
                && $job->refundOrders === false;
        });
    }

    public function test_handle_delegates_recurrence_exclusion_with_occurrence_start_date(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-07-20 14:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occurrence->shouldReceive('getEventId')->andReturn($eventId);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->with($occurrenceId)->andReturn($occurrence);
        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')->once()->andReturn($updatedOccurrence);
        $this->expectAttendeeCancelCalled($eventId, $occurrenceId);
        $this->exclusionService
            ->shouldReceive('addExclusions')
            ->once()
            ->with($eventId, ['2026-07-20 14:00:00']);

        $result = $this->handler->handle($eventId, $occurrenceId);

        $this->assertSame($updatedOccurrence, $result);
    }

    public function test_handle_throws_exception_when_occurrence_not_found(): void
    {
        $eventId = 1;
        $occurrenceId = 999;

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->with($occurrenceId)->andReturn(null);

        $this->occurrenceRepository->shouldNotReceive('updateFromArray');
        $this->exclusionService->shouldNotReceive('addExclusions');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($eventId, $occurrenceId);

        Event::assertNotDispatched(OccurrenceCancelledEvent::class);
    }

    public function test_handle_throws_when_occurrence_belongs_to_different_event(): void
    {
        $requestedEventId = 1;
        $foreignEventId = 999;
        $occurrenceId = 10;

        $foreignOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $foreignOccurrence->shouldReceive('getEventId')->andReturn($foreignEventId);

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->with($occurrenceId)->andReturn($foreignOccurrence);

        $this->occurrenceRepository->shouldNotReceive('updateFromArray');
        $this->exclusionService->shouldNotReceive('addExclusions');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($requestedEventId, $occurrenceId);

        Event::assertNotDispatched(OccurrenceCancelledEvent::class);
    }

    public function test_handle_dispatches_event_with_refund_flag_true(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occurrence->shouldReceive('getEventId')->andReturn($eventId);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->with($occurrenceId)->andReturn($occurrence);
        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')->once()->andReturn($updatedOccurrence);
        $this->expectAttendeeCancelCalled($eventId, $occurrenceId);
        $this->exclusionService->shouldReceive('addExclusions')->once();

        $this->handler->handle($eventId, $occurrenceId, refundOrders: true);

        Event::assertDispatched(OccurrenceCancelledEvent::class, function ($e) use ($eventId, $occurrenceId) {
            return $e->eventId === $eventId
                && $e->occurrenceId === $occurrenceId
                && $e->refundOrders === true;
        });
    }

    public function test_handle_dispatches_event_with_refund_flag_false(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occurrence->shouldReceive('getEventId')->andReturn($eventId);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->with($occurrenceId)->andReturn($occurrence);
        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')->once()->andReturn($updatedOccurrence);
        $this->expectAttendeeCancelCalled($eventId, $occurrenceId);
        $this->exclusionService->shouldReceive('addExclusions')->once();

        $this->handler->handle($eventId, $occurrenceId, refundOrders: false);

        Event::assertDispatched(OccurrenceCancelledEvent::class, function ($e) {
            return $e->refundOrders === false;
        });
    }

    public function test_it_returns_early_if_occurrence_already_cancelled(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::CANCELLED->name);
        $occurrence->shouldReceive('getEventId')->andReturn($eventId);

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->with($occurrenceId)->andReturn($occurrence);

        $this->occurrenceRepository->shouldNotReceive('updateFromArray');
        $this->cancelAttendeesService->shouldNotReceive('cancelForOccurrence');
        $this->exclusionService->shouldNotReceive('addExclusions');

        $result = $this->handler->handle($eventId, $occurrenceId, refundOrders: true);

        $this->assertSame($occurrence, $result);

        Event::assertNotDispatched(OccurrenceCancelledEvent::class);
        Bus::assertNotDispatched(SendOccurrenceCancellationEmailJob::class);
    }

    public function test_delegates_attendee_cancellation_to_service(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);
        $occurrence->shouldReceive('getEventId')->andReturn($eventId);

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->andReturn($occurrence);
        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')->once()->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $this->cancelAttendeesService
            ->shouldReceive('cancelForOccurrence')
            ->once()
            ->with($eventId, $occurrenceId)
            ->andReturn([
                'attendee_ids' => [],
                'sales_backed_count' => 0,
            ]);

        $this->exclusionService->shouldReceive('addExclusions')->once();

        $result = $this->handler->handle($eventId, $occurrenceId);
        $this->assertNotNull($result);

        Bus::assertNotDispatched(SendOccurrenceCancellationEmailJob::class);
    }
}
