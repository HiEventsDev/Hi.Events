<?php

namespace Tests\Unit\Services\Domain\EventLocation;

use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\Generated\EventLocationDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventLocationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use HiEvents\Services\Domain\EventLocation\EventLocationUpserter;
use HiEvents\Services\Domain\Location\LocationOwnershipValidator;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EventLocationUpserterTest extends TestCase
{
    private EventRepositoryInterface|MockInterface $eventRepository;

    private EventLocationRepositoryInterface|MockInterface $eventLocationRepository;

    private LocationOwnershipValidator|MockInterface $ownershipValidator;

    private HtmlPurifierService|MockInterface $purifier;

    private EventLocationUpserter $upserter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventLocationRepository = Mockery::mock(EventLocationRepositoryInterface::class);
        $this->ownershipValidator = Mockery::mock(LocationOwnershipValidator::class);
        $this->purifier = Mockery::mock(HtmlPurifierService::class);

        $this->upserter = new EventLocationUpserter(
            $this->eventRepository,
            $this->eventLocationRepository,
            $this->ownershipValidator,
            $this->purifier,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_for_event_in_person_validates_ownership_and_creates_row(): void
    {
        $data = new EventLocationData(
            type: LocationType::IN_PERSON,
            location_id: 42,
        );

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getOrganizerId')->andReturn(3);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 1, 'account_id' => 7])
            ->andReturn($event);

        $this->ownershipValidator
            ->shouldReceive('assertOwnedBy')
            ->once()
            ->with(42, 3, 7);

        $created = Mockery::mock(EventLocationDomainObject::class);

        $this->eventLocationRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs) {
                return $attrs[EventLocationDomainObjectAbstract::EVENT_ID] === 1
                    && $attrs[EventLocationDomainObjectAbstract::TYPE] === LocationType::IN_PERSON->name
                    && $attrs[EventLocationDomainObjectAbstract::LOCATION_ID] === 42
                    && $attrs[EventLocationDomainObjectAbstract::ONLINE_EVENT_CONNECTION_DETAILS] === null
                    && str_starts_with($attrs[EventLocationDomainObjectAbstract::SHORT_ID], 'el_');
            }))
            ->andReturn($created);

        $result = $this->upserter->createForEvent(1, 7, $data);

        $this->assertSame($created, $result);
    }

    public function test_create_for_event_online_nulls_location_id_and_purifies_details(): void
    {
        $data = new EventLocationData(
            type: LocationType::ONLINE,
            location_id: null,
            online_event_connection_details: '<script>x</script><p>Zoom</p>',
        );

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getOrganizerId')->andReturn(3);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 1, 'account_id' => 7])
            ->andReturn($event);

        $this->ownershipValidator
            ->shouldReceive('assertOwnedBy')
            ->once()
            ->with(null, 3, 7);

        $this->purifier
            ->shouldReceive('purify')
            ->once()
            ->with('<script>x</script><p>Zoom</p>')
            ->andReturn('<p>Zoom</p>');

        $created = Mockery::mock(EventLocationDomainObject::class);

        $this->eventLocationRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs) {
                return $attrs[EventLocationDomainObjectAbstract::TYPE] === LocationType::ONLINE->name
                    && $attrs[EventLocationDomainObjectAbstract::LOCATION_ID] === null
                    && $attrs[EventLocationDomainObjectAbstract::ONLINE_EVENT_CONNECTION_DETAILS] === '<p>Zoom</p>';
            }))
            ->andReturn($created);

        $result = $this->upserter->createForEvent(1, 7, $data);

        $this->assertSame($created, $result);
    }

    public function test_create_for_event_throws_when_event_missing(): void
    {
        $data = new EventLocationData(
            type: LocationType::IN_PERSON,
            location_id: 42,
        );

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 999, 'account_id' => 7])
            ->andReturn(null);

        $this->ownershipValidator->shouldNotReceive('assertOwnedBy');
        $this->eventLocationRepository->shouldNotReceive('create');

        $this->expectException(ResourceNotFoundException::class);

        $this->upserter->createForEvent(999, 7, $data);
    }

    public function test_create_for_event_throws_when_location_not_owned_by_organizer(): void
    {
        $data = new EventLocationData(
            type: LocationType::IN_PERSON,
            location_id: 999,
        );

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getOrganizerId')->andReturn(3);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($event);

        $this->ownershipValidator
            ->shouldReceive('assertOwnedBy')
            ->once()
            ->with(999, 3, 7)
            ->andThrow(new ResourceNotFoundException(__('Location :id not found', ['id' => 999])));

        $this->eventLocationRepository->shouldNotReceive('create');

        $this->expectException(ResourceNotFoundException::class);

        $this->upserter->createForEvent(1, 7, $data);
    }

    public function test_update_in_place_updates_existing_row(): void
    {
        $data = new EventLocationData(
            type: LocationType::IN_PERSON,
            location_id: 42,
        );

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getOrganizerId')->andReturn(3);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 1, 'account_id' => 7])
            ->andReturn($event);

        $this->ownershipValidator
            ->shouldReceive('assertOwnedBy')
            ->once()
            ->with(42, 3, 7);

        $this->eventLocationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventLocationDomainObjectAbstract::ID => 5,
                EventLocationDomainObjectAbstract::EVENT_ID => 1,
            ])
            ->andReturn(Mockery::mock(EventLocationDomainObject::class));

        $updated = Mockery::mock(EventLocationDomainObject::class);

        $this->eventLocationRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                5,
                Mockery::on(function (array $attrs) {
                    return $attrs[EventLocationDomainObjectAbstract::TYPE] === LocationType::IN_PERSON->name
                        && $attrs[EventLocationDomainObjectAbstract::LOCATION_ID] === 42
                        && $attrs[EventLocationDomainObjectAbstract::ONLINE_EVENT_CONNECTION_DETAILS] === null
                        && ! array_key_exists(EventLocationDomainObjectAbstract::SHORT_ID, $attrs);
                }),
            )
            ->andReturn($updated);

        $result = $this->upserter->updateInPlace(5, 1, 7, $data);

        $this->assertSame($updated, $result);
    }

    public function test_update_in_place_throws_when_event_location_id_belongs_to_another_event(): void
    {
        $data = new EventLocationData(type: LocationType::IN_PERSON, location_id: null);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getOrganizerId')->andReturn(3);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($event);

        $this->ownershipValidator->shouldReceive('assertOwnedBy');

        $this->eventLocationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventLocationDomainObjectAbstract::ID => 99,
                EventLocationDomainObjectAbstract::EVENT_ID => 1,
            ])
            ->andReturn(null);

        $this->eventLocationRepository->shouldNotReceive('updateFromArray');

        $this->expectException(ResourceNotFoundException::class);

        $this->upserter->updateInPlace(99, 1, 7, $data);
    }
}
