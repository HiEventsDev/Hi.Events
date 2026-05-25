<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\UpdateEventOccurrenceHandler;
use HiEvents\Services\Domain\EventLocation\EventLocationCleaner;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use HiEvents\Services\Domain\EventLocation\EventLocationUpserter;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateEventOccurrenceHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private EventLocationUpserter|MockInterface $eventLocationUpserter;

    private EventLocationCleaner|MockInterface $eventLocationCleaner;

    private DatabaseManager|MockInterface $databaseManager;

    private UpdateEventOccurrenceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventLocationUpserter = Mockery::mock(EventLocationUpserter::class);
        $this->eventLocationCleaner = Mockery::mock(EventLocationCleaner::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new UpdateEventOccurrenceHandler(
            $this->occurrenceRepository,
            $this->eventRepository,
            $this->eventLocationUpserter,
            $this->eventLocationCleaner,
            $this->databaseManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Convenience factory for the "existing occurrence" mock with the fields the
     * override-detection logic reads.
     */
    private function existingOccurrence(
        int $id = 10,
        string $startDate = '2026-06-01 10:00:00',
        ?string $endDate = '2026-06-01 18:00:00',
        ?int $capacity = 100,
        bool $isOverridden = false,
        string $status = EventOccurrenceStatus::ACTIVE->name,
        int $usedCapacity = 0,
        ?int $eventLocationId = null,
    ): MockInterface {
        $occ = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ->shouldReceive('getId')->andReturn($id);
        $occ->shouldReceive('getStartDate')->andReturn($startDate);
        $occ->shouldReceive('getEndDate')->andReturn($endDate);
        $occ->shouldReceive('getCapacity')->andReturn($capacity);
        $occ->shouldReceive('getIsOverridden')->andReturn($isOverridden);
        $occ->shouldReceive('getStatus')->andReturn($status);
        $occ->shouldReceive('getUsedCapacity')->andReturn($usedCapacity);
        $occ->shouldReceive('getEventLocationId')->andReturn($eventLocationId);

        return $occ;
    }

    public function test_flags_as_overridden_when_start_date_changes(): void
    {
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(id: $occurrenceId);

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-02 10:00:00', // moved by a day
            end_date: '2026-06-01 18:00:00',
            capacity: 100,
            label: 'Same label',
        );

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($occurrenceId, Mockery::on(fn (array $attrs) => $attrs[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] === true))
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_flags_as_overridden_when_end_date_changes(): void
    {
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(id: $occurrenceId);

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 20:00:00', // extended by 2 hours
            capacity: 100,
            label: null,
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($occurrenceId, Mockery::on(fn (array $attrs) => $attrs[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] === true))
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_flags_as_overridden_when_capacity_changes(): void
    {
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(id: $occurrenceId);

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 200, // changed from 100
            label: null,
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($occurrenceId, Mockery::on(fn (array $attrs) => $attrs[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] === true))
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_does_not_flag_as_overridden_for_label_only_change(): void
    {
        // A label-only edit shouldn't pin the occurrence against rule regenerates.
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(id: $occurrenceId, isOverridden: false);

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 100,
            label: 'Brand new label',
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($occurrenceId, Mockery::on(fn (array $attrs) => $attrs[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] === false))
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_preserves_override_flag_when_already_overridden(): void
    {
        // Once overridden, stays overridden regardless of which fields change now —
        // we don't un-override just because the user happened to save with
        // rule-aligned values.
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(id: $occurrenceId, isOverridden: true);

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 100,
            label: 'Label change only',
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($occurrenceId, Mockery::on(fn (array $attrs) => $attrs[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] === true))
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_does_not_flag_as_overridden_when_dates_are_same_instant_different_format(): void
    {
        // DateHelper::convertToUTC (used by UpdateEventOccurrenceAction) returns
        // "Mon Jun 15 2026 10:00:00 GMT+0000" style while DB-hydrated getStartDate()
        // returns SQL format. Plain strict string equality would mark these as
        // different even though they represent the same instant — regression test.
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(
            id: $occurrenceId,
            startDate: '2026-06-01 10:00:00',
            endDate: '2026-06-01 18:00:00',
            isOverridden: false,
        );

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: 'Mon Jun 01 2026 10:00:00 GMT+0000',
            end_date: 'Mon Jun 01 2026 18:00:00 GMT+0000',
            capacity: 100,
            label: 'New label',
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($occurrenceId, Mockery::on(fn (array $attrs) => $attrs[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] === false))
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_handle_does_not_write_status_when_capacity_unchanged_and_status_already_correct(): void
    {
        // CANCELLED is owned by the dedicated cancel/reactivate handlers.
        // For capacity-derived states (ACTIVE / SOLD_OUT) the handler only
        // writes STATUS when the new ceiling actually changes which side of
        // capacity the occurrence sits on. A label-only edit on an ACTIVE
        // occurrence with no capacity change must leave STATUS untouched.
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(
            id: $occurrenceId,
            capacity: 100,
            status: EventOccurrenceStatus::ACTIVE->name,
            usedCapacity: 10,
        );

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 100,
            label: 'New label',
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                Mockery::on(fn (array $attrs) => ! array_key_exists(EventOccurrenceDomainObjectAbstract::STATUS, $attrs)),
            )
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_handle_reactivates_sold_out_occurrence_when_capacity_increases_above_used(): void
    {
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(
            id: $occurrenceId,
            capacity: 50,
            status: EventOccurrenceStatus::SOLD_OUT->name,
            usedCapacity: 50,
        );

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 100, // raised — sold-out should clear
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                Mockery::on(fn (array $attrs) => ($attrs[EventOccurrenceDomainObjectAbstract::STATUS] ?? null) === EventOccurrenceStatus::ACTIVE->name
                    && $attrs[EventOccurrenceDomainObjectAbstract::CAPACITY] === 100),
            )
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_handle_reactivates_sold_out_occurrence_when_capacity_cleared_to_unlimited(): void
    {
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(
            id: $occurrenceId,
            capacity: 50,
            status: EventOccurrenceStatus::SOLD_OUT->name,
            usedCapacity: 50,
        );

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: null,
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                Mockery::on(fn (array $attrs) => ($attrs[EventOccurrenceDomainObjectAbstract::STATUS] ?? null) === EventOccurrenceStatus::ACTIVE->name
                    && $attrs[EventOccurrenceDomainObjectAbstract::CAPACITY] === null),
            )
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_handle_marks_active_occurrence_sold_out_when_capacity_drops_below_used(): void
    {
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(
            id: $occurrenceId,
            capacity: 100,
            status: EventOccurrenceStatus::ACTIVE->name,
            usedCapacity: 80,
        );

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 50, // below current usage
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                Mockery::on(fn (array $attrs) => ($attrs[EventOccurrenceDomainObjectAbstract::STATUS] ?? null) === EventOccurrenceStatus::SOLD_OUT->name
                    && $attrs[EventOccurrenceDomainObjectAbstract::CAPACITY] === 50),
            )
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_handle_does_not_write_status_for_cancelled_occurrence_even_when_capacity_changes(): void
    {
        // CANCELLED is the load-bearing exception — its lifecycle is owned by
        // the cancel/reactivate handlers. A capacity edit on a cancelled date
        // (rare, but possible via direct API call) must not silently flip it
        // back to ACTIVE.
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(
            id: $occurrenceId,
            capacity: 100,
            status: EventOccurrenceStatus::CANCELLED->name,
            usedCapacity: 0,
        );

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 200,
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                Mockery::on(fn (array $attrs) => ! array_key_exists(EventOccurrenceDomainObjectAbstract::STATUS, $attrs)),
            )
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_gains_override_calls_create_for_event(): void
    {
        // Occurrence had no event_location override (event_location_id: null);
        // DTO supplies new event_location → upserter creates a fresh row,
        // FK gets set, and override flag is pinned.
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(id: $occurrenceId, eventLocationId: null);

        $locationData = new EventLocationData(
            type: LocationType::IN_PERSON,
            location_id: 42,
        );

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 100,
            event_location: $locationData,
        );

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getAccountId')->andReturn(7);

        $createdEventLocation = Mockery::mock(EventLocationDomainObject::class);
        $createdEventLocation->shouldReceive('getId')->andReturn(500);

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $this->eventLocationUpserter
            ->shouldReceive('createForEvent')
            ->once()
            ->with($eventId, 7, $locationData)
            ->andReturn($createdEventLocation);

        $this->eventLocationUpserter->shouldNotReceive('updateInPlace');
        $this->eventLocationCleaner->shouldNotReceive('deleteIfOrphaned');

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                Mockery::on(fn (array $attrs) => $attrs[EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID] === 500
                    && $attrs[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] === true),
            )
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_edits_existing_override_calls_update_in_place(): void
    {
        // Occurrence already has an event_location_id; DTO supplies a new
        // event_location payload → upserter updates the existing row in place,
        // FK stays the same.
        $occurrenceId = 10;
        $eventId = 1;
        $existingEventLocationId = 5;

        $existing = $this->existingOccurrence(
            id: $occurrenceId,
            eventLocationId: $existingEventLocationId,
        );

        $locationData = new EventLocationData(
            type: LocationType::IN_PERSON,
            location_id: 42,
        );

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 100,
            event_location: $locationData,
        );

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getAccountId')->andReturn(7);

        $updatedEventLocation = Mockery::mock(EventLocationDomainObject::class);

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $this->eventLocationUpserter
            ->shouldReceive('updateInPlace')
            ->once()
            ->with($existingEventLocationId, $eventId, 7, $locationData)
            ->andReturn($updatedEventLocation);

        $this->eventLocationUpserter->shouldNotReceive('createForEvent');
        $this->eventLocationCleaner->shouldNotReceive('deleteIfOrphaned');

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                // FK stays pinned to the existing row — updateInPlace mutates the
                // row, doesn't create a new one.
                Mockery::on(fn (array $attrs) => $attrs[EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID] === $existingEventLocationId),
            )
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_clear_event_location_clears_fk_and_cleans_up(): void
    {
        // Occurrence has an override; DTO requests clear_event_location → FK
        // gets nulled and the cleaner runs to soft-delete if the row is no
        // longer referenced.
        $occurrenceId = 10;
        $eventId = 1;
        $existingEventLocationId = 5;

        $existing = $this->existingOccurrence(
            id: $occurrenceId,
            eventLocationId: $existingEventLocationId,
        );

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 100,
            clear_event_location: true,
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->eventLocationUpserter->shouldNotReceive('createForEvent');
        $this->eventLocationUpserter->shouldNotReceive('updateInPlace');

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                Mockery::on(fn (array $attrs) => $attrs[EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID] === null
                    && $attrs[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] === true),
            )
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $this->eventLocationCleaner
            ->shouldReceive('deleteIfOrphaned')
            ->once()
            ->with($existingEventLocationId);

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_clear_event_location_noop_when_no_existing_fk(): void
    {
        // No existing override → clear_event_location is a no-op for both the
        // upserter and the cleaner. Just a regular update.
        $occurrenceId = 10;
        $eventId = 1;

        $existing = $this->existingOccurrence(id: $occurrenceId, eventLocationId: null);

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 100,
            clear_event_location: true,
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);

        $this->eventLocationUpserter->shouldNotReceive('createForEvent');
        $this->eventLocationUpserter->shouldNotReceive('updateInPlace');
        $this->eventLocationCleaner->shouldNotReceive('deleteIfOrphaned');

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                Mockery::on(fn (array $attrs) => $attrs[EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID] === null),
            )
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }

    public function test_throws_when_occurrence_not_found(): void
    {
        $occurrenceId = 999;
        $eventId = 1;

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn(null);

        $this->occurrenceRepository->shouldNotReceive('updateFromArray');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($occurrenceId, $dto);
    }
}
