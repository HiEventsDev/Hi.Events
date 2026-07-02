<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\CreateEventOccurrenceHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use HiEvents\Services\Domain\EventLocation\EventLocationUpserter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateEventOccurrenceHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private EventLocationUpserter|MockInterface $eventLocationUpserter;

    private DatabaseManager|MockInterface $databaseManager;

    private CreateEventOccurrenceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventLocationUpserter = Mockery::mock(EventLocationUpserter::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new CreateEventOccurrenceHandler(
            $this->occurrenceRepository,
            $this->eventRepository,
            $this->eventLocationUpserter,
            $this->databaseManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creates_occurrence_inheriting_event_location_when_no_event_location_payload(): void
    {
        // No event_location override on the DTO — the occurrence should inherit
        // by carrying a null event_location_id. The upserter must not be touched.
        $dto = new UpsertEventOccurrenceDTO(
            event_id: 1,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            capacity: 100,
            label: 'Morning Session',
        );

        $expectedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->eventLocationUpserter->shouldNotReceive('createForEvent');
        $this->eventRepository->shouldNotReceive('findById');

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs) {
                return $attrs[EventOccurrenceDomainObjectAbstract::EVENT_ID] === 1
                    && $attrs[EventOccurrenceDomainObjectAbstract::START_DATE] === '2026-06-01 10:00:00'
                    && $attrs[EventOccurrenceDomainObjectAbstract::END_DATE] === '2026-06-01 18:00:00'
                    && $attrs[EventOccurrenceDomainObjectAbstract::STATUS] === EventOccurrenceStatus::ACTIVE->name
                    && $attrs[EventOccurrenceDomainObjectAbstract::CAPACITY] === 100
                    && $attrs[EventOccurrenceDomainObjectAbstract::USED_CAPACITY] === 0
                    && $attrs[EventOccurrenceDomainObjectAbstract::LABEL] === 'Morning Session'
                    && $attrs[EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID] === null
                    && str_starts_with($attrs[EventOccurrenceDomainObjectAbstract::SHORT_ID], 'oc_');
            }))
            ->andReturn($expectedOccurrence);

        $result = $this->handler->handle($dto);

        $this->assertSame($expectedOccurrence, $result);
    }

    public function test_creates_occurrence_with_in_person_override(): void
    {
        $locationData = new EventLocationData(
            type: LocationType::IN_PERSON,
            location_id: 42,
        );

        $dto = new UpsertEventOccurrenceDTO(
            event_id: 1,
            start_date: '2026-06-01 10:00:00',
            event_location: $locationData,
        );

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getAccountId')->andReturn(7);

        $createdEventLocation = Mockery::mock(EventLocationDomainObject::class);
        $createdEventLocation->shouldReceive('getId')->andReturn(99);

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($event);

        $this->eventLocationUpserter
            ->shouldReceive('createForEvent')
            ->once()
            ->with(1, 7, $locationData)
            ->andReturn($createdEventLocation);

        $expectedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs) {
                return $attrs[EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID] === 99
                    && $attrs[EventOccurrenceDomainObjectAbstract::EVENT_ID] === 1;
            }))
            ->andReturn($expectedOccurrence);

        $result = $this->handler->handle($dto);

        $this->assertSame($expectedOccurrence, $result);
    }

    public function test_creates_occurrence_with_online_override(): void
    {
        $locationData = new EventLocationData(
            type: LocationType::ONLINE,
            online_event_connection_details: '<p>Zoom link</p>',
        );

        $dto = new UpsertEventOccurrenceDTO(
            event_id: 1,
            start_date: '2026-06-01 10:00:00',
            event_location: $locationData,
        );

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getAccountId')->andReturn(7);

        $createdEventLocation = Mockery::mock(EventLocationDomainObject::class);
        $createdEventLocation->shouldReceive('getId')->andReturn(123);

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($event);

        $this->eventLocationUpserter
            ->shouldReceive('createForEvent')
            ->once()
            ->with(1, 7, $locationData)
            ->andReturn($createdEventLocation);

        $expectedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs) {
                return $attrs[EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID] === 123;
            }))
            ->andReturn($expectedOccurrence);

        $result = $this->handler->handle($dto);

        $this->assertSame($expectedOccurrence, $result);
    }

    public function test_throws_when_event_not_found_for_override(): void
    {
        // event_location is set but the event lookup throws — the upserter
        // never runs and the occurrence never gets created. Note the handler
        // also has an explicit `=== null` guard which is unreachable in
        // production because `findById`'s contract is non-nullable
        // (BaseRepository throws ModelNotFoundException), so the realistic
        // failure surface is the bubble-up.
        $dto = new UpsertEventOccurrenceDTO(
            event_id: 999,
            start_date: '2026-06-01 10:00:00',
            event_location: new EventLocationData(
                type: LocationType::IN_PERSON,
                location_id: 42,
            ),
        );

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->with(999)
            ->andThrow(new ModelNotFoundException);

        $this->eventLocationUpserter->shouldNotReceive('createForEvent');
        $this->occurrenceRepository->shouldNotReceive('create');

        $this->expectException(ModelNotFoundException::class);

        $this->handler->handle($dto);
    }
}
