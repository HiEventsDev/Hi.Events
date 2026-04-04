<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Jobs\Occurrence\RefundOccurrenceOrdersJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\CancelOccurrenceHandler;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use HiEvents\Exceptions\ResourceNotFoundException;
use Tests\TestCase;

class CancelOccurrenceHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;
    private EventRepositoryInterface|MockInterface $eventRepository;
    private DatabaseManager|MockInterface $databaseManager;
    private CancelOccurrenceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->handler = new CancelOccurrenceHandler(
            $this->occurrenceRepository,
            $this->eventRepository,
            $this->databaseManager,
        );
    }

    public function testHandleSetsStatusToCancelled(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::SINGLE->name);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn($occurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                [
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::CANCELLED->name,
                ]
            )
            ->andReturn($updatedOccurrence);

        $this->eventRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $this->eventRepository
            ->shouldNotReceive('updateFromArray');

        $result = $this->handler->handle($eventId, $occurrenceId);

        $this->assertSame($updatedOccurrence, $result);

        Event::assertDispatched(OccurrenceCancelledEvent::class, function ($e) use ($eventId, $occurrenceId) {
            return $e->eventId === $eventId && $e->occurrenceId === $occurrenceId;
        });
    }

    public function testHandleAddsExcludedDateForRecurringEvent(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'frequency' => 'weekly',
            'excluded_dates' => [],
        ]);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn($occurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                [
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::CANCELLED->name,
                ]
            )
            ->andReturn($updatedOccurrence);

        $this->eventRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $this->eventRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $eventId,
                [
                    EventDomainObjectAbstract::RECURRENCE_RULE => [
                        'frequency' => 'weekly',
                        'excluded_dates' => ['2026-06-15'],
                    ],
                ]
            );

        $result = $this->handler->handle($eventId, $occurrenceId);

        $this->assertSame($updatedOccurrence, $result);

        Event::assertDispatched(OccurrenceCancelledEvent::class, function ($e) use ($eventId, $occurrenceId) {
            return $e->eventId === $eventId && $e->occurrenceId === $occurrenceId;
        });
    }

    public function testHandleDoesNotAddExcludedDateForSingleEvent(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::SINGLE->name);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn($occurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                [
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::CANCELLED->name,
                ]
            )
            ->andReturn($updatedOccurrence);

        $this->eventRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $this->eventRepository
            ->shouldNotReceive('updateFromArray');

        $result = $this->handler->handle($eventId, $occurrenceId);

        $this->assertSame($updatedOccurrence, $result);
    }

    public function testHandleAppendsToExistingExcludedDatesForRecurringEvent(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-07-20 14:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'frequency' => 'weekly',
            'excluded_dates' => ['2026-06-15'],
        ]);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($occurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                [
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::CANCELLED->name,
                ]
            )
            ->andReturn($updatedOccurrence);

        $this->eventRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $this->eventRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $eventId,
                [
                    EventDomainObjectAbstract::RECURRENCE_RULE => [
                        'frequency' => 'weekly',
                        'excluded_dates' => ['2026-06-15', '2026-07-20'],
                    ],
                ]
            );

        $result = $this->handler->handle($eventId, $occurrenceId);

        $this->assertSame($updatedOccurrence, $result);
    }

    public function testHandleDoesNotDuplicateExcludedDateIfAlreadyPresent(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'frequency' => 'weekly',
            'excluded_dates' => ['2026-06-15'],
        ]);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($occurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                [
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::CANCELLED->name,
                ]
            )
            ->andReturn($updatedOccurrence);

        $this->eventRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $this->eventRepository
            ->shouldNotReceive('updateFromArray');

        $result = $this->handler->handle($eventId, $occurrenceId);

        $this->assertSame($updatedOccurrence, $result);
    }

    public function testHandleThrowsExceptionWhenOccurrenceNotFound(): void
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

        $this->occurrenceRepository
            ->shouldNotReceive('updateFromArray');

        $this->eventRepository
            ->shouldNotReceive('findByIdLocked');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($eventId, $occurrenceId);

        Event::assertNotDispatched(OccurrenceCancelledEvent::class);
    }

    public function testHandleHandlesRecurrenceRuleAsJsonString(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-08-01 09:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getRecurrenceRule')->andReturn(
            json_encode(['frequency' => 'daily', 'excluded_dates' => []])
        );

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($occurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                [
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::CANCELLED->name,
                ]
            )
            ->andReturn($updatedOccurrence);

        $this->eventRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $this->eventRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $eventId,
                [
                    EventDomainObjectAbstract::RECURRENCE_RULE => [
                        'frequency' => 'daily',
                        'excluded_dates' => ['2026-08-01'],
                    ],
                ]
            );

        $result = $this->handler->handle($eventId, $occurrenceId);

        $this->assertSame($updatedOccurrence, $result);
    }

    public function testHandleDispatchesRefundJobWhenRefundOrdersIsTrue(): void
    {
        Bus::fake();

        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::SINGLE->name);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($occurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->andReturn($updatedOccurrence);

        $this->eventRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->andReturn($event);

        $this->handler->handle($eventId, $occurrenceId, refundOrders: true);

        Bus::assertDispatched(RefundOccurrenceOrdersJob::class, function ($job) use ($eventId, $occurrenceId) {
            return $job->eventId === $eventId && $job->occurrenceId === $occurrenceId;
        });
    }

    public function testHandleDoesNotDispatchRefundJobWhenRefundOrdersIsFalse(): void
    {
        Bus::fake();

        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 10:00:00');
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::SINGLE->name);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($occurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->andReturn($updatedOccurrence);

        $this->eventRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->andReturn($event);

        $this->handler->handle($eventId, $occurrenceId, refundOrders: false);

        Bus::assertNotDispatched(RefundOccurrenceOrdersJob::class);
    }

    public function test_it_returns_early_if_occurrence_already_cancelled(): void
    {
        Bus::fake();

        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::CANCELLED->name);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn($occurrence);

        $this->occurrenceRepository->shouldNotReceive('updateFromArray');
        $this->eventRepository->shouldNotReceive('findByIdLocked');

        $result = $this->handler->handle($eventId, $occurrenceId, refundOrders: true);

        $this->assertSame($occurrence, $result);

        Event::assertNotDispatched(OccurrenceCancelledEvent::class);
        Bus::assertNotDispatched(RefundOccurrenceOrdersJob::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
