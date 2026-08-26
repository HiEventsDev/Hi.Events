<?php

namespace Tests\Unit\Services\Domain\Event;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Domain\Event\EventOccurrenceGeneratorService;
use HiEvents\Services\Domain\Event\RecurrenceRuleParserService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EventOccurrenceGeneratorServiceTest extends TestCase
{
    private EventOccurrenceGeneratorService $service;

    private RecurrenceRuleParserService $ruleParser;

    private EventOccurrenceRepositoryInterface $occurrenceRepository;

    private WaitlistEntryRepositoryInterface|MockInterface $waitlistEntryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ruleParser = Mockery::mock(RecurrenceRuleParserService::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->waitlistEntryRepository = Mockery::mock(WaitlistEntryRepositoryInterface::class);
        $this->waitlistEntryRepository->shouldReceive('updateWhere')->byDefault();

        $this->service = new EventOccurrenceGeneratorService(
            $this->ruleParser,
            $this->occurrenceRepository,
            $this->waitlistEntryRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockDbBatchQuery(
        array $occurrenceIdsWithOrders = [],
        array $occurrenceIdsWithAttendees = [],
    ): void {
        $orderItemsBuilder = Mockery::mock(Builder::class);
        $orderItemsBuilder->shouldReceive('whereIn')->andReturnSelf();
        $orderItemsBuilder->shouldReceive('whereNull')->andReturnSelf();
        $orderItemsBuilder->shouldReceive('distinct')->andReturnSelf();
        $orderItemsBuilder->shouldReceive('pluck')->andReturn(collect($occurrenceIdsWithOrders));

        $attendeesBuilder = Mockery::mock(Builder::class);
        $attendeesBuilder->shouldReceive('whereIn')->andReturnSelf();
        $attendeesBuilder->shouldReceive('whereNull')->andReturnSelf();
        $attendeesBuilder->shouldReceive('distinct')->andReturnSelf();
        $attendeesBuilder->shouldReceive('pluck')->andReturn(collect($occurrenceIdsWithAttendees));

        DB::shouldReceive('table')
            ->with('order_items')
            ->andReturn($orderItemsBuilder);
        DB::shouldReceive('table')
            ->with('attendees')
            ->andReturn($attendeesBuilder);
    }

    public function test_new_occurrences_are_bulk_inserted_when_none_exist(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                [
                    'start' => CarbonImmutable::parse('2025-03-01 10:00:00'),
                    'end' => CarbonImmutable::parse('2025-03-01 11:00:00'),
                    'capacity' => 100,
                ],
            ]));

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect());

        $this->occurrenceRepository
            ->shouldReceive('insert')
            ->with(Mockery::on(function ($rows) {
                return count($rows) === 1
                    && $rows[0][EventOccurrenceDomainObjectAbstract::EVENT_ID] === 1
                    && $rows[0][EventOccurrenceDomainObjectAbstract::START_DATE] === '2025-03-01 10:00:00'
                    && $rows[0][EventOccurrenceDomainObjectAbstract::END_DATE] === '2025-03-01 11:00:00'
                    && $rows[0][EventOccurrenceDomainObjectAbstract::STATUS] === EventOccurrenceStatus::ACTIVE->name
                    && $rows[0][EventOccurrenceDomainObjectAbstract::CAPACITY] === 100
                    && $rows[0][EventOccurrenceDomainObjectAbstract::USED_CAPACITY] === 0
                    && $rows[0][EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] === false
                    && $rows[0][EventOccurrenceDomainObjectAbstract::SHORT_ID] !== '';
            }))
            ->once()
            ->andReturn(true);

        $this->occurrenceRepository->shouldNotReceive('deleteWhere');
        $this->occurrenceRepository->shouldNotReceive('updateWhere');

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_inserts_are_chunked(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $candidates = collect(range(0, 500))->map(fn (int $i) => [
            'start' => CarbonImmutable::parse('2025-03-01 10:00:00')->addDays($i),
            'end' => null,
            'capacity' => null,
        ]);

        $this->ruleParser
            ->shouldReceive('parse')
            ->once()
            ->andReturn($candidates);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect());

        $insertedCounts = [];
        $this->occurrenceRepository
            ->shouldReceive('insert')
            ->twice()
            ->andReturnUsing(function (array $rows) use (&$insertedCounts) {
                $insertedCounts[] = count($rows);

                return true;
            });

        $this->service->generate($event, $recurrenceRule);

        $this->assertSame([500, 1], $insertedCounts);
    }

    public function test_changed_existing_occurrence_is_updated_without_refetch(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                [
                    'start' => CarbonImmutable::parse('2025-03-01 10:00:00'),
                    'end' => CarbonImmutable::parse('2025-03-01 12:00:00'),
                    'capacity' => 200,
                ],
            ]));

        $existingOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$existingOccurrence]));

        $this->mockDbBatchQuery([]);

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->with(
                Mockery::on(function ($attributes) {
                    return $attributes[EventOccurrenceDomainObjectAbstract::START_DATE] === '2025-03-01 10:00:00'
                        && $attributes[EventOccurrenceDomainObjectAbstract::END_DATE] === '2025-03-01 12:00:00'
                        && $attributes[EventOccurrenceDomainObjectAbstract::CAPACITY] === 200;
                }),
                [EventOccurrenceDomainObjectAbstract::ID => 5]
            )
            ->once();

        $this->occurrenceRepository->shouldNotReceive('findById');
        $this->occurrenceRepository->shouldNotReceive('insert');
        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_unchanged_existing_occurrence_is_not_updated(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                [
                    'start' => CarbonImmutable::parse('2025-03-01 10:00:00'),
                    'end' => CarbonImmutable::parse('2025-03-01 11:00:00'),
                    'capacity' => 100,
                    'label' => 'Morning',
                ],
            ]));

        $existingOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
            capacity: 100,
            label: 'Morning',
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$existingOccurrence]));

        $this->mockDbBatchQuery([]);

        $this->occurrenceRepository->shouldNotReceive('updateWhere');
        $this->occurrenceRepository->shouldNotReceive('insert');
        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_unchanged_occurrence_with_iso8601_hydrated_dates_is_not_updated(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                [
                    'start' => CarbonImmutable::parse('2025-03-01 10:00:00'),
                    'end' => CarbonImmutable::parse('2025-03-01 11:00:00'),
                    'capacity' => null,
                ],
            ]));

        $existingOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01T10:00:00.000000Z',
            endDate: '2025-03-01T11:00:00.000000Z',
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$existingOccurrence]));

        $this->mockDbBatchQuery([]);

        $this->occurrenceRepository->shouldNotReceive('updateWhere');
        $this->occurrenceRepository->shouldNotReceive('insert');
        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_existing_occurrence_with_orders_is_not_modified(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                [
                    'start' => CarbonImmutable::parse('2025-03-01 10:00:00'),
                    'end' => CarbonImmutable::parse('2025-03-01 12:00:00'),
                    'capacity' => 200,
                ],
            ]));

        $existingOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$existingOccurrence]));

        $this->mockDbBatchQuery([5]);

        $this->occurrenceRepository->shouldNotReceive('updateWhere');
        $this->occurrenceRepository->shouldNotReceive('insert');
        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_existing_overridden_occurrence_is_not_modified(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                [
                    'start' => CarbonImmutable::parse('2025-03-01 10:00:00'),
                    'end' => CarbonImmutable::parse('2025-03-01 12:00:00'),
                    'capacity' => 200,
                ],
            ]));

        $existingOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
            isOverridden: true,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$existingOccurrence]));

        $this->mockDbBatchQuery([]);

        $this->occurrenceRepository->shouldNotReceive('updateWhere');
        $this->occurrenceRepository->shouldNotReceive('insert');
        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_stale_occurrence_with_no_orders_and_not_overridden_is_deleted(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                [
                    'start' => CarbonImmutable::parse('2025-03-02 10:00:00'),
                    'end' => CarbonImmutable::parse('2025-03-02 11:00:00'),
                    'capacity' => 100,
                ],
            ]));

        $staleOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$staleOccurrence]));

        $this->mockDbBatchQuery([]);

        $this->occurrenceRepository
            ->shouldReceive('insert')
            ->once()
            ->andReturn(true);

        $this->occurrenceRepository
            ->shouldReceive('deleteWhere')
            ->with([[EventOccurrenceDomainObjectAbstract::ID, 'in', [5]]])
            ->once();

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_stale_occurrences_in_use_are_marked_overridden_in_one_update(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect());

        $staleWithOrders = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
        );
        $staleWithAttendees = $this->createOccurrenceDomainObject(
            id: 6,
            startDate: '2025-03-02 10:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$staleWithOrders, $staleWithAttendees]));

        $this->mockDbBatchQuery(occurrenceIdsWithOrders: [5], occurrenceIdsWithAttendees: [6]);

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => true],
                [[EventOccurrenceDomainObjectAbstract::ID, 'in', [5, 6]]],
            );

        $this->occurrenceRepository->shouldNotReceive('deleteWhere');
        $this->occurrenceRepository->shouldNotReceive('insert');

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_stale_overridden_occurrence_is_not_deleted(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect());

        $staleOverridden = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            isOverridden: true,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect([$staleOverridden]));

        $this->mockDbBatchQuery([]);

        $this->occurrenceRepository->shouldNotReceive('deleteWhere');
        $this->occurrenceRepository->shouldNotReceive('updateWhere');

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_stale_cancelled_occurrence_is_kept(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect());

        $staleCancelled = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            status: EventOccurrenceStatus::CANCELLED->name,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect([$staleCancelled]));

        $this->mockDbBatchQuery([]);

        $this->occurrenceRepository->shouldNotReceive('deleteWhere');
        $this->occurrenceRepository->shouldNotReceive('updateWhere');

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_mixed_scenario_with_new_updated_skipped_and_stale_occurrences(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $candidates = collect([
            [
                'start' => CarbonImmutable::parse('2025-03-01 10:00:00'),
                'end' => CarbonImmutable::parse('2025-03-01 11:00:00'),
                'capacity' => 100,
            ],
            [
                'start' => CarbonImmutable::parse('2025-03-02 10:00:00'),
                'end' => CarbonImmutable::parse('2025-03-02 11:00:00'),
                'capacity' => 100,
            ],
            [
                'start' => CarbonImmutable::parse('2025-03-03 10:00:00'),
                'end' => CarbonImmutable::parse('2025-03-03 11:00:00'),
                'capacity' => 100,
            ],
            [
                'start' => CarbonImmutable::parse('2025-03-05 10:00:00'),
                'end' => CarbonImmutable::parse('2025-03-05 11:00:00'),
                'capacity' => 100,
            ],
        ]);

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn($candidates);

        $existingUpdatable = $this->createOccurrenceDomainObject(
            id: 1, startDate: '2025-03-01 10:00:00', endDate: '2025-03-01 10:30:00',
        );
        $existingWithOrders = $this->createOccurrenceDomainObject(
            id: 2, startDate: '2025-03-02 10:00:00', endDate: '2025-03-02 10:30:00',
        );
        $existingOverridden = $this->createOccurrenceDomainObject(
            id: 3, startDate: '2025-03-03 10:00:00', endDate: '2025-03-03 10:30:00', isOverridden: true,
        );
        $existingStale = $this->createOccurrenceDomainObject(
            id: 4, startDate: '2025-03-04 10:00:00', endDate: '2025-03-04 10:30:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$existingUpdatable, $existingWithOrders, $existingOverridden, $existingStale]));

        $this->mockDbBatchQuery([2]);

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->with(
                Mockery::on(function ($attributes) {
                    return $attributes[EventOccurrenceDomainObjectAbstract::END_DATE] === '2025-03-01 11:00:00'
                        && $attributes[EventOccurrenceDomainObjectAbstract::CAPACITY] === 100;
                }),
                [EventOccurrenceDomainObjectAbstract::ID => 1]
            )
            ->once();

        $this->occurrenceRepository
            ->shouldReceive('insert')
            ->with(Mockery::on(function ($rows) {
                return count($rows) === 1
                    && $rows[0][EventOccurrenceDomainObjectAbstract::START_DATE] === '2025-03-05 10:00:00';
            }))
            ->once()
            ->andReturn(true);

        $this->occurrenceRepository
            ->shouldReceive('deleteWhere')
            ->with([[EventOccurrenceDomainObjectAbstract::ID, 'in', [4]]])
            ->once();

        $this->occurrenceRepository->shouldNotReceive('findById');

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_event_timezone_is_passed_to_parser(): void
    {
        $event = $this->createMockEvent(timezone: 'America/New_York');
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'America/New_York')
            ->once()
            ->andReturn(collect());

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect());

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_null_timezone_defaults_to_utc(): void
    {
        $event = $this->createMockEvent(timezone: null);
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect());

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect());

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_new_occurrence_with_null_end_date(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                ['start' => CarbonImmutable::parse('2025-03-01 10:00:00'), 'end' => null, 'capacity' => null],
            ]));

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect());

        $this->occurrenceRepository
            ->shouldReceive('insert')
            ->with(Mockery::on(function ($rows) {
                return $rows[0][EventOccurrenceDomainObjectAbstract::END_DATE] === null
                    && $rows[0][EventOccurrenceDomainObjectAbstract::CAPACITY] === null;
            }))
            ->once()
            ->andReturn(true);

        $this->service->generate($event, $recurrenceRule);
    }

    public function test_stale_occurrence_waitlist_entries_are_cancelled_before_deletion(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect());

        $stale = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect([$stale]));

        $this->mockDbBatchQuery([]);

        $this->waitlistEntryRepository = Mockery::mock(WaitlistEntryRepositoryInterface::class);
        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->with(
                ['status' => WaitlistEntryStatus::CANCELLED->name],
                [
                    ['event_id', 'in', [1]],
                    ['event_occurrence_id', 'in', [5]],
                    ['status', 'in', [
                        WaitlistEntryStatus::WAITING->name,
                        WaitlistEntryStatus::OFFERED->name,
                    ]],
                ],
            )
            ->once();

        $this->service = new EventOccurrenceGeneratorService(
            $this->ruleParser,
            $this->occurrenceRepository,
            $this->waitlistEntryRepository,
        );

        $this->occurrenceRepository
            ->shouldReceive('deleteWhere')
            ->with([[EventOccurrenceDomainObjectAbstract::ID, 'in', [5]]])
            ->once();

        $this->service->generate($event, $recurrenceRule);
    }

    private function createMockEvent(int $id = 1, ?string $timezone = 'UTC'): EventDomainObject
    {
        $mock = Mockery::mock(EventDomainObject::class);
        $mock->shouldReceive('getId')->andReturn($id);
        $mock->shouldReceive('getTimezone')->andReturn($timezone);

        return $mock;
    }

    private function createOccurrenceDomainObject(
        int $id,
        string $startDate,
        ?string $endDate = null,
        bool $isOverridden = false,
        ?int $capacity = null,
        ?string $label = null,
        ?string $status = null,
        int $eventId = 1,
    ): EventOccurrenceDomainObject {
        $occ = new EventOccurrenceDomainObject;
        $occ->setId($id);
        $occ->setEventId($eventId);
        $occ->setShortId('oc_test'.$id);
        $occ->setStartDate($startDate);
        $occ->setEndDate($endDate);
        $occ->setIsOverridden($isOverridden);
        $occ->setCapacity($capacity);
        $occ->setLabel($label);
        $occ->setStatus($status ?? EventOccurrenceStatus::ACTIVE->name);

        return $occ;
    }
}
