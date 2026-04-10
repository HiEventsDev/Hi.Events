<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Enums\BulkOccurrenceAction;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Jobs\Occurrence\BulkCancelOccurrencesJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\BulkUpdateOccurrencesHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\BulkUpdateOccurrencesDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BulkUpdateOccurrencesHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;
    private OrderItemRepositoryInterface|MockInterface $orderItemRepository;
    private DatabaseManager|MockInterface $databaseManager;
    private BulkUpdateOccurrencesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->handler = new BulkUpdateOccurrencesHandler(
            $this->occurrenceRepository,
            $this->orderItemRepository,
            $this->databaseManager,
        );
    }

    public function testHandleUpdatesCapacityForFutureNonOverriddenOccurrences(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'America/New_York',
            capacity: 500,
            future_only: true,
            skip_overridden: true,
        );

        $futureOccurrence = $this->createOccurrenceMock(10, false, false);
        $pastOccurrence = $this->createOccurrenceMock(11, true, false);
        $overriddenOccurrence = $this->createOccurrenceMock(12, false, true);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$futureOccurrence, $pastOccurrence, $overriddenOccurrence]));

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [EventOccurrenceDomainObjectAbstract::CAPACITY => 500],
                [[EventOccurrenceDomainObjectAbstract::ID, 'in', [10]]]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result);
    }

    public function testHandleShiftsTimeByMinutes(): void
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

        $this->assertEquals(1, $result);
    }

    public function testHandleShiftsTimeBackwards(): void
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

        $this->assertEquals(1, $result);
    }

    public function testHandleShiftsOnlyStartTimeWhenEndTimeShiftIsNull(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            start_time_shift: 90,
            future_only: false,
            skip_overridden: false,
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
                        && !array_key_exists(EventOccurrenceDomainObjectAbstract::END_DATE, $attributes);
                }),
                [EventOccurrenceDomainObjectAbstract::ID => 10]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result);
    }

    public function testHandleShiftTimesDoesNotAddEndDateWhenNull(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            start_time_shift: 60,
            end_time_shift: 60,
            future_only: false,
            skip_overridden: false,
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
                        && !array_key_exists(EventOccurrenceDomainObjectAbstract::END_DATE, $attributes);
                }),
                [EventOccurrenceDomainObjectAbstract::ID => 10]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result);
    }

    public function testHandleCancelsAllFutureOccurrencesViaJob(): void
    {
        Bus::fake([BulkCancelOccurrencesJob::class]);

        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::CANCEL,
            timezone: 'UTC',
            future_only: true,
            skip_overridden: false,
            refund_orders: true,
        );

        $futureOccurrence1 = $this->createOccurrenceMock(10, false, false, '2026-03-15 09:00:00');
        $futureOccurrence2 = $this->createOccurrenceMock(11, false, true, '2026-03-22 09:00:00');
        $pastOccurrence = $this->createOccurrenceMock(12, true, false);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$futureOccurrence1, $futureOccurrence2, $pastOccurrence]));

        $result = $this->handler->handle($dto);

        $this->assertEquals(2, $result);

        Bus::assertDispatched(BulkCancelOccurrencesJob::class, function (BulkCancelOccurrencesJob $job) {
            return $job->eventId === 1
                && $job->occurrenceIds === [10, 11]
                && $job->refundOrders === true;
        });
    }

    public function testHandleSkipsCancelledOccurrences(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            capacity: 100,
            future_only: false,
            skip_overridden: false,
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
                [EventOccurrenceDomainObjectAbstract::CAPACITY => 100],
                [[EventOccurrenceDomainObjectAbstract::ID, 'in', [10]]]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result);
    }

    public function testHandleReturnsZeroWhenNoFieldsToUpdate(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            future_only: false,
            skip_overridden: false,
        );

        $occurrence = $this->createOccurrenceMock(10, false, false);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occurrence]));

        $this->occurrenceRepository
            ->shouldNotReceive('updateWhere');

        $result = $this->handler->handle($dto);

        $this->assertEquals(0, $result);
    }

    public function testHandleClearsCapacity(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::UPDATE,
            timezone: 'UTC',
            clear_capacity: true,
            future_only: false,
            skip_overridden: false,
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
                [EventOccurrenceDomainObjectAbstract::CAPACITY => null],
                [[EventOccurrenceDomainObjectAbstract::ID, 'in', [10]]]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result);
    }

    public function testHandleFiltersToSpecificOccurrenceIds(): void
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
                [EventOccurrenceDomainObjectAbstract::CAPACITY => 200],
                [[EventOccurrenceDomainObjectAbstract::ID, 'in', [10, 12]]]
            );

        $result = $this->handler->handle($dto);

        $this->assertEquals(2, $result);
    }

    public function testHandleDeletesOccurrencesWithoutOrders(): void
    {
        $dto = new BulkUpdateOccurrencesDTO(
            event_id: 1,
            action: BulkOccurrenceAction::DELETE,
            timezone: 'UTC',
            future_only: false,
            skip_overridden: false,
            occurrence_ids: [10, 11],
        );

        $occNoOrders = $this->createOccurrenceMock(10, false, false);
        $occWithOrders = $this->createOccurrenceMock(11, false, false);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$occNoOrders, $occWithOrders]));

        $this->orderItemRepository
            ->shouldReceive('countWhere')
            ->with(['event_occurrence_id' => 10])
            ->once()
            ->andReturn(0);

        $this->orderItemRepository
            ->shouldReceive('countWhere')
            ->with(['event_occurrence_id' => 11])
            ->once()
            ->andReturn(5);

        $this->occurrenceRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([[EventOccurrenceDomainObjectAbstract::ID, 'in', [10]]]);

        $result = $this->handler->handle($dto);

        $this->assertEquals(1, $result);
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
