<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DeleteEventOccurrenceHandler;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DeleteEventOccurrenceHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private OrderItemRepositoryInterface|MockInterface $orderItemRepository;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private WaitlistEntryRepositoryInterface|MockInterface $waitlistEntryRepository;

    private DatabaseManager|MockInterface $databaseManager;

    private DeleteEventOccurrenceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->waitlistEntryRepository = Mockery::mock(WaitlistEntryRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        // Default: waitlist cleanup is a no-op (no entries). Tests that
        // exercise the cleanup branch override this.
        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->byDefault()
            ->andReturn(0);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new DeleteEventOccurrenceHandler(
            $this->occurrenceRepository,
            $this->eventRepository,
            $this->orderItemRepository,
            $this->attendeeRepository,
            $this->waitlistEntryRepository,
            $this->databaseManager,
        );
    }

    public function test_handle_successfully_deletes_occurrence_with_no_orders(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn($occurrence);

        $this->orderItemRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['event_occurrence_id' => $occurrenceId])
            ->andReturn(0);

        $this->attendeeRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['event_occurrence_id' => $occurrenceId])
            ->andReturn(0);

        $this->occurrenceRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
            ]);

        // Non-recurring event: excluded_dates step is a no-op.
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::SINGLE->name);
        $this->eventRepository
            ->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository->shouldNotReceive('updateFromArray');

        $this->handler->handle($eventId, $occurrenceId);

        $this->assertTrue(true);
    }

    public function test_delete_adds_date_to_recurrence_excluded_dates_for_recurring_event(): void
    {
        // Without this, the next regenerate would parse the rule, see the same
        // candidate date, and recreate the deleted occurrence — silently
        // undoing the delete.
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($occurrence);
        $this->orderItemRepository->shouldReceive('countWhere')->once()->andReturn(0);
        $this->attendeeRepository->shouldReceive('countWhere')->once()->andReturn(0);
        $this->occurrenceRepository->shouldReceive('deleteWhere')->once();

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'excluded_dates' => ['2026-05-01'],
        ]);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($eventId, Mockery::on(static function (array $attrs): bool {
                $rule = $attrs[EventDomainObjectAbstract::RECURRENCE_RULE] ?? null;

                return is_array($rule)
                    && $rule['excluded_dates'] === ['2026-05-01']
                    && $rule['excluded_occurrences'] === ['2026-06-15 10:00'];
            }))
            ->andReturn(Mockery::mock(EventDomainObject::class));

        $this->handler->handle($eventId, $occurrenceId);

        $this->assertTrue(true);
    }

    public function test_delete_does_not_duplicate_existing_excluded_date(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($occurrence);
        $this->orderItemRepository->shouldReceive('countWhere')->once()->andReturn(0);
        $this->attendeeRepository->shouldReceive('countWhere')->once()->andReturn(0);
        $this->occurrenceRepository->shouldReceive('deleteWhere')->once();

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'excluded_dates' => ['2026-06-15'],
            'excluded_occurrences' => ['2026-06-15 10:00'],
        ]);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        // No event update — the date is already excluded.
        $this->eventRepository->shouldNotReceive('updateFromArray');

        $this->handler->handle($eventId, $occurrenceId);

        $this->assertTrue(true);
    }

    public function test_handle_throws_validation_exception_when_occurrence_has_orders(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn($occurrence);

        $this->orderItemRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['event_occurrence_id' => $occurrenceId])
            ->andReturn(5);

        $this->occurrenceRepository
            ->shouldNotReceive('deleteWhere');

        $this->expectException(ValidationException::class);

        $this->handler->handle($eventId, $occurrenceId);
    }

    public function test_handle_throws_exception_when_occurrence_not_found(): void
    {
        $eventId = 1;
        $occurrenceId = 999;

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn(null);

        $this->orderItemRepository
            ->shouldNotReceive('countWhere');

        $this->occurrenceRepository
            ->shouldNotReceive('deleteWhere');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($eventId, $occurrenceId);
    }

    public function test_delete_cancels_waiting_and_offered_waitlist_entries_for_occurrence(): void
    {
        // The FK is nullOnDelete — without an explicit cancel here, WAITING
        // entries pointing at this occurrence would be left as orphans (their
        // event_occurrence_id nulled), which crashes ProcessWaitlistService
        // on the next CapacityChangedEvent for recurring events.
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($occurrence);
        $this->orderItemRepository->shouldReceive('countWhere')->once()->andReturn(0);
        $this->attendeeRepository->shouldReceive('countWhere')->once()->andReturn(0);
        $this->occurrenceRepository->shouldReceive('deleteWhere')->once();

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::SINGLE->name);
        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository->shouldNotReceive('updateFromArray');

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(static function (array $attrs): bool {
                    return $attrs === ['status' => WaitlistEntryStatus::CANCELLED->name];
                }),
                Mockery::on(static function (array $where) use ($eventId, $occurrenceId): bool {
                    if (($where['event_id'] ?? null) !== $eventId) {
                        return false;
                    }
                    if (($where['event_occurrence_id'] ?? null) !== $occurrenceId) {
                        return false;
                    }
                    foreach ($where as $clause) {
                        if (is_array($clause) && $clause[0] === 'status' && $clause[1] === 'in') {
                            return $clause[2] === [
                                WaitlistEntryStatus::WAITING->name,
                                WaitlistEntryStatus::OFFERED->name,
                            ];
                        }
                    }

                    return false;
                }),
            )
            ->andReturn(0);

        $this->handler->handle($eventId, $occurrenceId);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
