<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\ReactivateOccurrenceHandler;
use HiEvents\Services\Domain\Event\RecurrenceRuleExclusionService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ReactivateOccurrenceHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private RecurrenceRuleExclusionService|MockInterface $exclusionService;

    private DatabaseManager|MockInterface $databaseManager;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private ReactivateOccurrenceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->exclusionService = Mockery::mock(RecurrenceRuleExclusionService::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new ReactivateOccurrenceHandler(
            $this->occurrenceRepository,
            $this->exclusionService,
            $this->databaseManager,
            $this->attendeeRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reactivates_cancelled_occurrence_and_only_touches_status(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getEventId')->andReturn($eventId);
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::CANCELLED->name);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-01 10:00:00');

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->with($occurrenceId)->andReturn($occurrence);

        $this->attendeeRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with([
                AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrenceId,
                AttendeeDomainObjectAbstract::STATUS => AttendeeStatus::CANCELLED->name,
            ])
            ->andReturn(0);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($occurrenceId, [
                EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::ACTIVE->name,
            ])
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $this->exclusionService
            ->shouldReceive('removeExclusion')
            ->once()
            ->with($eventId, '2026-06-01 10:00:00');

        $result = $this->handler->handle($eventId, $occurrenceId);

        $this->assertNotNull($result);
    }

    public function test_rejects_reactivation_of_non_cancelled_occurrence(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getEventId')->andReturn($eventId);
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->andReturn($occurrence);

        $this->occurrenceRepository->shouldNotReceive('updateFromArray');
        $this->exclusionService->shouldNotReceive('removeExclusion');

        $this->expectException(ValidationException::class);

        $this->handler->handle($eventId, $occurrenceId);
    }

    public function test_blocks_reactivation_when_occurrence_has_cancelled_attendees(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getEventId')->andReturn($eventId);
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::CANCELLED->name);

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->with($occurrenceId)->andReturn($occurrence);

        $this->attendeeRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with([
                AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrenceId,
                AttendeeDomainObjectAbstract::STATUS => AttendeeStatus::CANCELLED->name,
            ])
            ->andReturn(3);

        $this->occurrenceRepository->shouldNotReceive('updateFromArray');
        $this->exclusionService->shouldNotReceive('removeExclusion');

        $this->expectException(ValidationException::class);

        $this->handler->handle($eventId, $occurrenceId);
    }

    public function test_throws_when_occurrence_not_found_for_event(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(eventId: 1, occurrenceId: 99);
    }

    public function test_throws_when_occurrence_belongs_to_different_event(): void
    {
        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getEventId')->andReturn(2);

        $this->occurrenceRepository
            ->shouldReceive('findByIdLocked')->once()->andReturn($occurrence);

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(eventId: 1, occurrenceId: 10);
    }
}
