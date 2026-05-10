<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\UpdateEventOccurrenceHandler;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateEventOccurrenceHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private DatabaseManager|MockInterface $databaseManager;

    private UpdateEventOccurrenceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new UpdateEventOccurrenceHandler(
            $this->occurrenceRepository,
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
    ): MockInterface {
        $occ = Mockery::mock(EventOccurrenceDomainObject::class);
        $occ->shouldReceive('getId')->andReturn($id);
        $occ->shouldReceive('getStartDate')->andReturn($startDate);
        $occ->shouldReceive('getEndDate')->andReturn($endDate);
        $occ->shouldReceive('getCapacity')->andReturn($capacity);
        $occ->shouldReceive('getIsOverridden')->andReturn($isOverridden);
        $occ->shouldReceive('getStatus')->andReturn($status);
        // SOLD_OUT/ACTIVE reconciliation in the handler reads used_capacity to
        // decide whether the new ceiling has headroom.
        $occ->shouldReceive('getUsedCapacity')->andReturn($usedCapacity);

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
        // ProductQuantityUpdateService::increaseOccurrenceUsedCapacity flips
        // ACTIVE → SOLD_OUT when usage crosses the ceiling. The reverse path
        // only runs from decreaseOccurrenceUsedCapacity, so a capacity edit
        // that raises the ceiling above current usage is the only place the
        // generic update handler can re-open a sold-out date.
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
        // Unlimited capacity (null) can never be sold out.
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

    public function test_handle_throws_exception_when_occurrence_not_found(): void
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

        $result = $this->handler->handle($occurrenceId, $dto);
        $this->assertNotNull($result);
    }
}
