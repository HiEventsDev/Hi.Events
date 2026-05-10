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
use Illuminate\Support\Collection;
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
        // Most tests don't exercise stale-deletion; default to a no-op so the
        // few that do can override with explicit expectations.
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

    /**
     * Mocks the two batch lookups the generator runs to decide which existing
     * occurrences are "in use" and therefore protected from soft-deletion:
     * occurrences pointed at by an active order_item OR an active attendee.
     */
    private function mockDbBatchQuery(
        array $occurrenceIdsWithOrders = [],
        array $occurrenceIdsWithAttendees = [],
    ): void {
        $orderItemsBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $orderItemsBuilder->shouldReceive('whereIn')->andReturnSelf();
        $orderItemsBuilder->shouldReceive('whereNull')->andReturnSelf();
        $orderItemsBuilder->shouldReceive('distinct')->andReturnSelf();
        $orderItemsBuilder->shouldReceive('pluck')->andReturn(collect($occurrenceIdsWithOrders));

        $attendeesBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
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

    public function testNewOccurrencesAreCreatedWhenNoneExist(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $candidateStart = CarbonImmutable::parse('2025-03-01 10:00:00');
        $candidateEnd = CarbonImmutable::parse('2025-03-01 11:00:00');

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                ['start' => $candidateStart, 'end' => $candidateEnd, 'capacity' => 100],
            ]));

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect());

        $createdOccurrence = $this->createOccurrenceDomainObject(
            id: 10,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($arg) {
                return $arg[EventOccurrenceDomainObjectAbstract::EVENT_ID] === 1
                    && $arg[EventOccurrenceDomainObjectAbstract::START_DATE] === '2025-03-01 10:00:00'
                    && $arg[EventOccurrenceDomainObjectAbstract::END_DATE] === '2025-03-01 11:00:00'
                    && $arg[EventOccurrenceDomainObjectAbstract::STATUS] === EventOccurrenceStatus::ACTIVE->name
                    && $arg[EventOccurrenceDomainObjectAbstract::CAPACITY] === 100
                    && $arg[EventOccurrenceDomainObjectAbstract::USED_CAPACITY] === 0
                    && $arg[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] === false;
            }))
            ->once()
            ->andReturn($createdOccurrence);

        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(1, $result);
        $this->assertEquals(10, $result->first()->getId());
    }

    public function testMultipleNewOccurrencesCreated(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $candidates = collect([
            [
                'start' => CarbonImmutable::parse('2025-03-01 10:00:00'),
                'end' => CarbonImmutable::parse('2025-03-01 11:00:00'),
                'capacity' => 50,
            ],
            [
                'start' => CarbonImmutable::parse('2025-03-02 10:00:00'),
                'end' => CarbonImmutable::parse('2025-03-02 11:00:00'),
                'capacity' => 50,
            ],
        ]);

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn($candidates);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect());

        $occ1 = $this->createOccurrenceDomainObject(id: 10, startDate: '2025-03-01 10:00:00');
        $occ2 = $this->createOccurrenceDomainObject(id: 11, startDate: '2025-03-02 10:00:00');

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->twice()
            ->andReturn($occ1, $occ2);

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(2, $result);
    }

    public function testExistingOccurrenceWithoutOrdersAndNotOverriddenIsUpdatedInPlace(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $candidateStart = CarbonImmutable::parse('2025-03-01 10:00:00');
        $candidateEnd = CarbonImmutable::parse('2025-03-01 12:00:00');

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                ['start' => $candidateStart, 'end' => $candidateEnd, 'capacity' => 200],
            ]));

        $existingOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
            isOverridden: false,
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

        $updatedOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 12:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('findById')
            ->with(5)
            ->once()
            ->andReturn($updatedOccurrence);

        $this->occurrenceRepository->shouldNotReceive('create');
        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(1, $result);
        $this->assertEquals(5, $result->first()->getId());
        $this->assertEquals('2025-03-01 12:00:00', $result->first()->getEndDate());
    }

    public function testExistingOccurrenceWithOrdersIsNotModified(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $candidateStart = CarbonImmutable::parse('2025-03-01 10:00:00');
        $candidateEnd = CarbonImmutable::parse('2025-03-01 12:00:00');

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                ['start' => $candidateStart, 'end' => $candidateEnd, 'capacity' => 200],
            ]));

        $existingOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
            isOverridden: false,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$existingOccurrence]));

        $this->mockDbBatchQuery([5]);

        $this->occurrenceRepository->shouldNotReceive('updateWhere');
        $this->occurrenceRepository->shouldNotReceive('findById');
        $this->occurrenceRepository->shouldNotReceive('create');
        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(1, $result);
        $this->assertEquals(5, $result->first()->getId());
        $this->assertEquals('2025-03-01 11:00:00', $result->first()->getEndDate());
    }

    public function testExistingOverriddenOccurrenceIsNotModified(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $candidateStart = CarbonImmutable::parse('2025-03-01 10:00:00');
        $candidateEnd = CarbonImmutable::parse('2025-03-01 12:00:00');

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                ['start' => $candidateStart, 'end' => $candidateEnd, 'capacity' => 200],
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
        $this->occurrenceRepository->shouldNotReceive('findById');
        $this->occurrenceRepository->shouldNotReceive('create');
        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(1, $result);
        $this->assertEquals(5, $result->first()->getId());
        $this->assertEquals('2025-03-01 11:00:00', $result->first()->getEndDate());
    }

    public function testStaleOccurrenceWithNoOrdersAndNotOverriddenIsSoftDeleted(): void
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
            isOverridden: false,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$staleOccurrence]));

        $this->mockDbBatchQuery([]);

        $newOccurrence = $this->createOccurrenceDomainObject(
            id: 10,
            startDate: '2025-03-02 10:00:00',
            endDate: '2025-03-02 11:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($newOccurrence);

        $this->occurrenceRepository
            ->shouldReceive('deleteWhere')
            ->with([[EventOccurrenceDomainObjectAbstract::ID, 'in', [5]]])
            ->once();

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(1, $result);
        $this->assertEquals(10, $result->first()->getId());
    }

    public function testStaleOccurrenceWithOrdersIsMarkedOverriddenAndNotDeleted(): void
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

        $staleWithOrders = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
            isOverridden: false,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$staleWithOrders]));

        $this->mockDbBatchQuery([5]);

        $newOccurrence = $this->createOccurrenceDomainObject(
            id: 10,
            startDate: '2025-03-02 10:00:00',
            endDate: '2025-03-02 11:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($newOccurrence);

        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => true],
                [EventOccurrenceDomainObjectAbstract::ID => 5],
            );

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(1, $result);
        $this->assertEquals(10, $result->first()->getId());
    }

    public function testStaleOccurrenceWithAttendeesButNoOrderItemsIsMarkedOverriddenAndNotDeleted(): void
    {
        // Regression: matches the single/bulk delete handlers, which both
        // refuse to delete an occurrence that has any attendees pointing at
        // it — even if no order_item row does. Regeneration was previously
        // only checking order_items, so changing the recurrence rule could
        // soft-delete an attendee-bearing occurrence in import / partial-
        // restore scenarios where the two tables disagree.
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

        $staleWithAttendees = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
            isOverridden: false,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$staleWithAttendees]));

        // No order_item rows pointing at occ 5, but the attendees query does
        // return it — must still be protected.
        $this->mockDbBatchQuery(occurrenceIdsWithOrders: [], occurrenceIdsWithAttendees: [5]);

        $newOccurrence = $this->createOccurrenceDomainObject(
            id: 10,
            startDate: '2025-03-02 10:00:00',
            endDate: '2025-03-02 11:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($newOccurrence);

        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => true],
                [EventOccurrenceDomainObjectAbstract::ID => 5],
            );

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(1, $result);
        $this->assertEquals(10, $result->first()->getId());
    }

    public function testStaleOverriddenOccurrenceIsNotDeleted(): void
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

        $staleOverridden = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
            isOverridden: true,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->with([EventOccurrenceDomainObjectAbstract::EVENT_ID => 1])
            ->once()
            ->andReturn(collect([$staleOverridden]));

        $this->mockDbBatchQuery([]);

        $newOccurrence = $this->createOccurrenceDomainObject(
            id: 10,
            startDate: '2025-03-02 10:00:00',
            endDate: '2025-03-02 11:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($newOccurrence);

        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(1, $result);
    }

    public function testMixedScenarioWithNewUpdatedSkippedAndStaleOccurrences(): void
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
            id: 1, startDate: '2025-03-01 10:00:00', endDate: '2025-03-01 10:30:00', isOverridden: false,
        );
        $existingWithOrders = $this->createOccurrenceDomainObject(
            id: 2, startDate: '2025-03-02 10:00:00', endDate: '2025-03-02 10:30:00', isOverridden: false,
        );
        $existingOverridden = $this->createOccurrenceDomainObject(
            id: 3, startDate: '2025-03-03 10:00:00', endDate: '2025-03-03 10:30:00', isOverridden: true,
        );
        $existingStale = $this->createOccurrenceDomainObject(
            id: 4, startDate: '2025-03-04 10:00:00', endDate: '2025-03-04 10:30:00', isOverridden: false,
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
                    return $attributes[EventOccurrenceDomainObjectAbstract::START_DATE] === '2025-03-01 10:00:00'
                        && $attributes[EventOccurrenceDomainObjectAbstract::END_DATE] === '2025-03-01 11:00:00'
                        && $attributes[EventOccurrenceDomainObjectAbstract::CAPACITY] === 100;
                }),
                [EventOccurrenceDomainObjectAbstract::ID => 1]
            )
            ->once();

        $updatedOcc1 = $this->createOccurrenceDomainObject(
            id: 1, startDate: '2025-03-01 10:00:00', endDate: '2025-03-01 11:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($updatedOcc1);

        $newOcc = $this->createOccurrenceDomainObject(
            id: 20, startDate: '2025-03-05 10:00:00', endDate: '2025-03-05 11:00:00',
        );

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($newOcc);

        $this->occurrenceRepository
            ->shouldReceive('deleteWhere')
            ->with([[EventOccurrenceDomainObjectAbstract::ID, 'in', [4]]])
            ->once();

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(4, $result);

        $ids = $result->map(fn ($occ) => $occ->getId())->toArray();
        $this->assertContains(1, $ids);
        $this->assertContains(2, $ids);
        $this->assertContains(3, $ids);
        $this->assertContains(20, $ids);
        $this->assertNotContains(4, $ids);
    }

    public function testEventTimezoneIsPassedToParser(): void
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

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(0, $result);
    }

    public function testNullTimezoneDefaultsToUtc(): void
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

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(0, $result);
    }

    public function testNewOccurrenceWithNullEndDate(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $candidateStart = CarbonImmutable::parse('2025-03-01 10:00:00');

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                ['start' => $candidateStart, 'end' => null, 'capacity' => null],
            ]));

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect());

        $createdOccurrence = $this->createOccurrenceDomainObject(
            id: 10,
            startDate: '2025-03-01 10:00:00',
            endDate: null,
        );

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($arg) {
                return $arg[EventOccurrenceDomainObjectAbstract::END_DATE] === null
                    && $arg[EventOccurrenceDomainObjectAbstract::CAPACITY] === null;
            }))
            ->once()
            ->andReturn($createdOccurrence);

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(1, $result);
        $this->assertNull($result->first()->getEndDate());
    }

    public function testEmptyCandidatesWithExistingOccurrencesDeletesStale(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect());

        $staleOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            isOverridden: false,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect([$staleOccurrence]));

        $this->mockDbBatchQuery([]);

        $this->occurrenceRepository
            ->shouldReceive('deleteWhere')
            ->with([[EventOccurrenceDomainObjectAbstract::ID, 'in', [5]]])
            ->once();

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(0, $result);
    }

    public function testStaleOccurrenceWaitlistEntriesAreCancelledBeforeDeletion(): void
    {
        // Regression for the regenerate-strands-waitlist bug: removeStaleOccurrences
        // soft-deletes orphaned occurrences. The FK is nullOnDelete which only
        // fires on hard deletes, so without explicit waitlist cleanup, WAITING/
        // OFFERED entries are left pointing at soft-deleted rows and crash
        // ProcessWaitlistService on the next CapacityChangedEvent.
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
            isOverridden: false,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect([$stale]));

        $this->mockDbBatchQuery([]);

        // Override the default no-op mock with a strict expectation: the
        // waitlist cancel must run with the right scope before the delete.
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

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(0, $result);
    }

    public function testEmptyCandidatesWithOverriddenExistingOccurrenceKeepsIt(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect());

        $overriddenOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            isOverridden: true,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect([$overriddenOccurrence]));

        $this->mockDbBatchQuery([]);

        $this->occurrenceRepository->shouldNotReceive('deleteWhere');

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(0, $result);
    }

    public function testExistingOccurrenceWithOrdersAndOverriddenIsSkipped(): void
    {
        $event = $this->createMockEvent();
        $recurrenceRule = ['frequency' => 'daily'];

        $candidateStart = CarbonImmutable::parse('2025-03-01 10:00:00');
        $candidateEnd = CarbonImmutable::parse('2025-03-01 12:00:00');

        $this->ruleParser
            ->shouldReceive('parse')
            ->with($recurrenceRule, 'UTC')
            ->once()
            ->andReturn(collect([
                ['start' => $candidateStart, 'end' => $candidateEnd, 'capacity' => 200],
            ]));

        $existingOccurrence = $this->createOccurrenceDomainObject(
            id: 5,
            startDate: '2025-03-01 10:00:00',
            endDate: '2025-03-01 11:00:00',
            isOverridden: true,
        );

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect([$existingOccurrence]));

        $this->mockDbBatchQuery([5]);

        $this->occurrenceRepository->shouldNotReceive('updateWhere');
        $this->occurrenceRepository->shouldNotReceive('findById');

        $result = $this->service->generate($event, $recurrenceRule);

        $this->assertCount(1, $result);
        $this->assertSame($existingOccurrence, $result->first());
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
        int $eventId = 1,
    ): EventOccurrenceDomainObject {
        $occ = new EventOccurrenceDomainObject();
        $occ->setId($id);
        $occ->setEventId($eventId);
        $occ->setShortId('oc_test' . $id);
        $occ->setStartDate($startDate);
        $occ->setEndDate($endDate);
        $occ->setIsOverridden($isOverridden);
        $occ->setCapacity($capacity);

        return $occ;
    }
}
