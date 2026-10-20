<?php

namespace Tests\Unit\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventLocationDTO;
use HiEvents\Services\Application\Handlers\Event\UpdateEventLocationHandler;
use HiEvents\Services\Domain\EventLocation\EventLocationCleaner;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use HiEvents\Services\Domain\EventLocation\EventLocationUpserter;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateEventLocationHandlerTest extends TestCase
{
    private EventRepositoryInterface|MockInterface $eventRepository;

    private EventLocationUpserter|MockInterface $eventLocationUpserter;

    private EventLocationCleaner|MockInterface $eventLocationCleaner;

    private UpdateEventLocationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventRepository->shouldReceive('loadRelation')->zeroOrMoreTimes()->andReturnSelf()->byDefault();
        $this->eventLocationUpserter = Mockery::mock(EventLocationUpserter::class);
        $this->eventLocationCleaner = Mockery::mock(EventLocationCleaner::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new UpdateEventLocationHandler(
            $this->eventRepository,
            $this->eventLocationUpserter,
            $this->eventLocationCleaner,
            $databaseManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creates_event_location_when_event_has_none(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventLocationId')->andReturn(null);

        $reloaded = Mockery::mock(EventDomainObject::class);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($event, $reloaded);

        $created = Mockery::mock(EventLocationDomainObject::class);
        $created->shouldReceive('getId')->andReturn(42);

        $data = new EventLocationData(type: LocationType::IN_PERSON, location_id: 7);

        $this->eventLocationUpserter
            ->shouldReceive('createForEvent')
            ->once()
            ->with(1, 5, $data)
            ->andReturn($created);

        $this->eventRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(['event_location_id' => 42], ['id' => 1, 'account_id' => 5]);

        $this->eventLocationCleaner->shouldNotReceive('deleteIfOrphaned');

        $dto = new UpdateEventLocationDTO(event_id: 1, account_id: 5, event_location: $data);

        $this->assertSame($reloaded, $this->handler->handle($dto));
    }

    public function test_updates_existing_event_location_in_place(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventLocationId')->andReturn(99);

        $reloaded = Mockery::mock(EventDomainObject::class);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($event, $reloaded);

        $data = new EventLocationData(type: LocationType::IN_PERSON, location_id: 7);

        $this->eventLocationUpserter
            ->shouldReceive('updateInPlace')
            ->once()
            ->with(99, 1, 5, $data)
            ->andReturn(Mockery::mock(EventLocationDomainObject::class));

        $this->eventRepository->shouldNotReceive('updateWhere');
        $this->eventLocationCleaner->shouldNotReceive('deleteIfOrphaned');

        $dto = new UpdateEventLocationDTO(event_id: 1, account_id: 5, event_location: $data);

        $this->assertSame($reloaded, $this->handler->handle($dto));
    }

    public function test_clear_nulls_fk_and_cleans_orphan(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventLocationId')->andReturn(99);

        $reloaded = Mockery::mock(EventDomainObject::class);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($event, $reloaded);

        $this->eventRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(['event_location_id' => null], ['id' => 1, 'account_id' => 5]);

        $this->eventLocationCleaner
            ->shouldReceive('deleteIfOrphaned')
            ->once()
            ->with(99);

        $dto = new UpdateEventLocationDTO(event_id: 1, account_id: 5, clear_event_location: true);

        $this->assertSame($reloaded, $this->handler->handle($dto));
    }

    public function test_clear_noop_when_event_has_no_location(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventLocationId')->andReturn(null);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($event, $event);

        $this->eventRepository->shouldNotReceive('updateWhere');
        $this->eventLocationCleaner->shouldNotReceive('deleteIfOrphaned');
        $this->eventLocationUpserter->shouldNotReceive('createForEvent');
        $this->eventLocationUpserter->shouldNotReceive('updateInPlace');

        $dto = new UpdateEventLocationDTO(event_id: 1, account_id: 5, clear_event_location: true);

        $this->assertSame($event, $this->handler->handle($dto));
    }

    public function test_throws_when_event_not_found(): void
    {
        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(new UpdateEventLocationDTO(event_id: 999, account_id: 5));
    }
}
