<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\BulkOccurrenceAction;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderItemDomainObjectAbstract;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Jobs\Occurrence\BulkCancelOccurrencesJob;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\BulkUpdateOccurrencesHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\BulkUpdateOccurrencesDTO;
use HiEvents\Services\Domain\Event\RecurrenceRuleExclusionService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BulkUpdateOccurrencesHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private OrderItemRepositoryInterface|MockInterface $orderItemRepository;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private WaitlistEntryRepositoryInterface|MockInterface $waitlistEntryRepository;

    private RecurrenceRuleExclusionService|MockInterface $exclusionService;

    private DatabaseManager|MockInterface $databaseManager;

    private BulkUpdateOccurrencesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->waitlistEntryRepository = Mockery::mock(WaitlistEntryRepositoryInterface::class);
        $this->exclusionService = Mockery::mock(RecurrenceRuleExclusionService::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        // Default: no attendees on any occurrence. Delete-path tests that need
        // to assert the attendee guard override this expectation.
        $this->attendeeRepository
            ->shouldReceive('findWhereIn')
            ->byDefault()
            ->andReturn(new Collection);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn ($callback) => $callback());

        // Default: exclusion update is a no-op. The DELETE branch overrides this
        // to assert the call.
        $this->exclusionService->shouldReceive('addExclusions')->byDefault();

        // Default: waitlist cleanup is a no-op. Tests targeting the cleanup
        // branch override this expectation.
        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->byDefault()
            ->andReturn(0);

        // Capacity edits trigger SOLD_OUT/ACTIVE reconciliation via two extra
        // scoped updateWheres on top of the main attribute update. They're
        // a side effect — each test still asserts its specific main updateWhere
        // explicitly, so default the reconciliation to a no-op here so it
        // doesn't trigger NoMatchingExpectationException for unrelated tests.
        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->with(
                Mockery::on(fn (array $attrs) => array_keys($attrs) === [EventOccurrenceDomainObjectAbstract::STATUS]),
                Mockery::any(),
            )
            ->zeroOrMoreTimes()
            ->byDefault();

        $this->handler = new BulkUpdateOccurrencesHandler(
            $this->occurrenceRepository,
            $this->orderItemRepository,
            $this->attendeeRepository,
            $this->waitlistEntryRepository,
            $this->exclusionService,
            $this->databaseManager,
        );
    }

    public function test_handle_updates_capacity_for_future_non_overridden_occurrences(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'America/New_York',
            capacity: 500,
            future_only: true,
            skip_overridden: true,
            apply_to_all: true,
        );

        $futureOccurrence = $this->createOccurrenceMock(10, false, false);
        $pastOccurrence = $this->createOccurrenceMock(11, true, false);
        $overriddenOccurrence = $this->createOccurrenceMock(12, false, true);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$futureOccurrence, $pastOccurrence, $overriddenOccurrence]));

        // Capacity changes pin the occurrence as overridden so future regenerates
        // don't reset it back to the rule's default.
        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [
                    EventOccurrenceDomainObjectAbstract::CAPACITY => 500,
                    EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => true,
                ],
                [[EventOccurrenceDomainObjectAbstract::ID, 'in', [10]]]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result->updated_count);
    }

    public function test_handle_shifts_time_by_minutes(): void
    {
        // Occurrence stored as 09:00 UTC, shift forward by 60 minutes → 10:00 UTC
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'America/New_York',
            start_time_shift: 60,
            end_time_shift: 60,
            future_only: false,
            skip_overridden: false,
            apply_to_all: true,
        );

        $occurrence = $this->createOccurrenceMock(10, false, false, '2026-03-01 14:00:00', '2026-03-01 16:00:00');

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occurrence]));

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attributes) {
                    return $attributes[EventOccurrenceDomainObjectAbstract::START_DATE] === '2026-03-01 15:00:00'
                        && $attributes[EventOccurrenceDomainObjectAbstract::END_DATE] === '2026-03-01 17:00:00';
                }),
                [EventOccurrenceDomainObjectAbstract::ID => 10]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result->updated_count);
    }

    public function test_time_shift_pins_as_overridden_so_regenerate_doesnt_revert_it(): void
    {
        // Without is_overridden, the next regenerate would treat the shifted
        // occurrence as stale (no candidate at its new time) and delete it.
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            start_time_shift: 60,
            future_only: false,
            skip_overridden: false,
            apply_to_all: true,
        );

        $occurrence = $this->createOccurrenceMock(10, false, false, '2026-03-01 14:00:00', null);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occurrence]));

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn (array $attrs) => $attrs[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] === true),
                Mockery::any(),
            );

        $result = $this->handler->handle($dto);
        $this->assertEquals(1, $result->updated_count);
    }

    public function test_handle_shifts_time_backwards(): void
    {
        // Shift backward by 30 minutes: 14:00 → 13:30, 16:00 → 15:30
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            start_time_shift: -30,
            end_time_shift: -30,
            future_only: false,
            skip_overridden: false,
            apply_to_all: true,
        );

        $occurrence = $this->createOccurrenceMock(10, false, false, '2026-03-01 14:00:00', '2026-03-01 16:00:00');

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occurrence]));

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attributes) {
                    return $attributes[EventOccurrenceDomainObjectAbstract::START_DATE] === '2026-03-01 13:30:00'
                        && $attributes[EventOccurrenceDomainObjectAbstract::END_DATE] === '2026-03-01 15:30:00';
                }),
                [EventOccurrenceDomainObjectAbstract::ID => 10]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result->updated_count);
    }

    public function test_handle_shifts_only_start_time_when_end_time_shift_is_null(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            start_time_shift: 90,
            future_only: false,
            skip_overridden: false,
            apply_to_all: true,
        );

        $occurrence = $this->createOccurrenceMock(10, false, false, '2026-03-01 09:00:00', '2026-03-01 11:00:00');

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occurrence]));

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attributes) {
                    return $attributes[EventOccurrenceDomainObjectAbstract::START_DATE] === '2026-03-01 10:30:00'
                        && ! array_key_exists(EventOccurrenceDomainObjectAbstract::END_DATE, $attributes);
                }),
                [EventOccurrenceDomainObjectAbstract::ID => 10]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result->updated_count);
    }

    public function test_handle_shift_times_does_not_add_end_date_when_null(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            start_time_shift: 60,
            end_time_shift: 60,
            future_only: false,
            skip_overridden: false,
            apply_to_all: true,
        );

        $occurrence = $this->createOccurrenceMock(10, false, false, '2026-03-01 14:00:00', null);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occurrence]));

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attributes) {
                    return $attributes[EventOccurrenceDomainObjectAbstract::START_DATE] === '2026-03-01 15:00:00'
                        && ! array_key_exists(EventOccurrenceDomainObjectAbstract::END_DATE, $attributes);
                }),
                [EventOccurrenceDomainObjectAbstract::ID => 10]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result->updated_count);
    }

    public function test_handle_cancels_all_future_occurrences_via_job(): void
    {
        Bus::fake([BulkCancelOccurrencesJob::class]);

        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::CANCEL,
            timezone: 'UTC',
            future_only: true,
            skip_overridden: false,
            refund_orders: true,
            apply_to_all: true,
        );

        $futureOccurrence1 = $this->createOccurrenceMock(10, false, false, '2026-03-15 09:00:00');
        $futureOccurrence2 = $this->createOccurrenceMock(11, false, true, '2026-03-22 09:00:00');
        $pastOccurrence = $this->createOccurrenceMock(12, true, false);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$futureOccurrence1, $futureOccurrence2, $pastOccurrence]));

        $result = $this->handler->handle($dto);

        $this->assertEquals(2, $result->updated_count);

        Bus::assertDispatched(BulkCancelOccurrencesJob::class, function (BulkCancelOccurrencesJob $job) {
            return $job->eventId === 1
                && $job->occurrenceIds === [10, 11]
                && $job->refundOrders === true;
        });
    }

    public function test_handle_skips_cancelled_occurrences(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            capacity: 100,
            future_only: false,
            skip_overridden: false,
            apply_to_all: true,
        );

        $activeOccurrence = $this->createOccurrenceMock(10, false, false, '2026-03-01 09:00:00', null, EventOccurrenceStatus::ACTIVE->name);
        $cancelledOccurrence = $this->createOccurrenceMock(11, false, false, '2026-03-02 09:00:00', null, EventOccurrenceStatus::CANCELLED->name);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$activeOccurrence, $cancelledOccurrence]));

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [
                    EventOccurrenceDomainObjectAbstract::CAPACITY => 100,
                    EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => true,
                ],
                [[EventOccurrenceDomainObjectAbstract::ID, 'in', [10]]]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result->updated_count);
    }

    public function test_handle_returns_zero_when_no_fields_to_update(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            future_only: false,
            skip_overridden: false,
            apply_to_all: true,
        );

        $occurrence = $this->createOccurrenceMock(10, false, false);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occurrence]));

        $this->occurrenceRepository
            ->shouldNotReceive('updateWhere');

        $result = $this->handler->handle($dto);

        $this->assertEquals(0, $result->updated_count);
    }

    public function test_handle_clears_capacity(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            clear_capacity: true,
            future_only: false,
            skip_overridden: false,
            apply_to_all: true,
        );

        $occurrence = $this->createOccurrenceMock(10, false, false);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occurrence]));

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [
                    EventOccurrenceDomainObjectAbstract::CAPACITY => null,
                    EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => true,
                ],
                [[EventOccurrenceDomainObjectAbstract::ID, 'in', [10]]]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result->updated_count);
    }

    public function test_handle_filters_to_specific_occurrence_ids(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            capacity: 200,
            future_only: false,
            skip_overridden: false,
            occurrence_ids: [10, 12],
        );

        $occ10 = $this->createOccurrenceMock(10, false, false);
        $occ11 = $this->createOccurrenceMock(11, false, false);
        $occ12 = $this->createOccurrenceMock(12, false, false);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occ10, $occ11, $occ12]));

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [
                    EventOccurrenceDomainObjectAbstract::CAPACITY => 200,
                    EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => true,
                ],
                [[EventOccurrenceDomainObjectAbstract::ID, 'in', [10, 12]]]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(2, $result->updated_count);
    }

    public function test_handle_deletes_occurrences_without_orders(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::DELETE,
            timezone: 'UTC',
            future_only: false,
            skip_overridden: false,
            occurrence_ids: [10, 11],
        );

        $occNoOrders = $this->createOccurrenceMock(10, false, false, '2026-03-01 09:00:00');
        $occWithOrders = $this->createOccurrenceMock(11, false, false, '2026-03-08 09:00:00');

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occNoOrders, $occWithOrders]));

        // Batched: a single findWhereIn returns one row per occurrence-with-orders.
        // Occurrence 11 has orders, occurrence 10 doesn't.
        $orderItem11 = Mockery::mock(OrderItemDomainObject::class);
        $orderItem11->shouldReceive('getEventOccurrenceId')->andReturn(11);

        $this->orderItemRepository
            ->shouldReceive('findWhereIn')
            ->once()
            ->withArgs(function ($field, $values, $additionalWhere = [], $columns = []) {
                return $field === OrderItemDomainObjectAbstract::EVENT_OCCURRENCE_ID
                    && $values === [10, 11]
                    && $columns === [OrderItemDomainObjectAbstract::EVENT_OCCURRENCE_ID];
            })
            ->andReturn(new Collection([$orderItem11]));

        $this->occurrenceRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([[EventOccurrenceDomainObjectAbstract::ID, 'in', [10]]]);

        $this->exclusionService
            ->shouldReceive('addExclusions')
            ->once()
            ->with(1, ['2026-03-01 09:00:00']);

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result->updated_count);
    }

    public function test_handle_skips_deletion_for_occurrences_with_attendees_but_no_orders(): void
    {
        // Mirrors the single-delete handler's attendee guard. Attendees can
        // exist without order_items in legacy/imported data, so checking only
        // orders would soft-delete an occurrence that still has live attendees.
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::DELETE,
            timezone: 'UTC',
            future_only: false,
            skip_overridden: false,
            occurrence_ids: [10, 11],
        );

        $occClean = $this->createOccurrenceMock(10, false, false, '2026-03-01 09:00:00');
        $occWithAttendees = $this->createOccurrenceMock(11, false, false, '2026-03-08 09:00:00');

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occClean, $occWithAttendees]));

        // Batched orders lookup: no occurrences have orders.
        $this->orderItemRepository
            ->shouldReceive('findWhereIn')
            ->once()
            ->withArgs(function ($field, $values, $additionalWhere = [], $columns = []) {
                return $field === OrderItemDomainObjectAbstract::EVENT_OCCURRENCE_ID
                    && $values === [10, 11]
                    && $columns === [OrderItemDomainObjectAbstract::EVENT_OCCURRENCE_ID];
            })
            ->andReturn(new Collection);

        // Override default attendee no-op: occurrence 11 has attendees and
        // must be excluded from the delete batch even though it has no orders.
        $attendee11 = Mockery::mock(AttendeeDomainObject::class);
        $attendee11->shouldReceive('getEventOccurrenceId')->andReturn(11);

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->attendeeRepository
            ->shouldReceive('findWhereIn')
            ->once()
            ->withArgs(function ($field, $values, $additionalWhere = [], $columns = []) {
                return $field === AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID
                    && $values === [10, 11]
                    && $columns === [AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID];
            })
            ->andReturn(new Collection([$attendee11]));

        $this->handler = new BulkUpdateOccurrencesHandler(
            $this->occurrenceRepository,
            $this->orderItemRepository,
            $this->attendeeRepository,
            $this->waitlistEntryRepository,
            $this->exclusionService,
            $this->databaseManager,
        );

        $this->occurrenceRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([[EventOccurrenceDomainObjectAbstract::ID, 'in', [10]]]);

        $this->exclusionService
            ->shouldReceive('addExclusions')
            ->once()
            ->with(1, ['2026-03-01 09:00:00']);

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result->updated_count);
    }

    private function createOccurrenceMock(
        int $id,
        bool $isPast,
        bool $isOverridden,
        string $startDate = '2026-03-01 09:00:00',
        ?string $endDate = '2026-03-01 11:00:00',
        string $status = 'ACTIVE',
    ): EventOccurrenceDomainObject|MockInterface {
        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('isPast')->andReturn($isPast);
        $occurrence->shouldReceive('getIsOverridden')->andReturn($isOverridden);
        $occurrence->shouldReceive('getId')->andReturn($id);
        $occurrence->shouldReceive('getStatus')->andReturn($status);
        $occurrence->shouldReceive('getStartDate')->andReturn($startDate);
        $occurrence->shouldReceive('getEndDate')->andReturn($endDate);

        return $occurrence;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
