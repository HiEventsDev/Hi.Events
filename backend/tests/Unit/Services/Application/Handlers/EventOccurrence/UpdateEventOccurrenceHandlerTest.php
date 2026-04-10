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
            ->andReturnUsing(fn($callback) => $callback());

        $this->handler = new UpdateEventOccurrenceHandler(
            $this->occurrenceRepository,
            $this->databaseManager,
        );
    }

    public function testHandleSuccessfullyUpdatesOccurrenceWithIsOverriddenTrue(): void
    {
        $occurrenceId = 10;
        $eventId = 1;

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 200,
            label: 'Updated Session',
        );

        $existingOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $existingOccurrence->shouldReceive('getId')->andReturn($occurrenceId);
        $existingOccurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::ACTIVE->name);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn($existingOccurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                [
                    EventOccurrenceDomainObjectAbstract::START_DATE => '2026-06-01 10:00:00',
                    EventOccurrenceDomainObjectAbstract::END_DATE => '2026-06-01 18:00:00',
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::ACTIVE->name,
                    EventOccurrenceDomainObjectAbstract::CAPACITY => 200,
                    EventOccurrenceDomainObjectAbstract::LABEL => 'Updated Session',
                    EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => true,
                ]
            )
            ->andReturn($updatedOccurrence);

        $result = $this->handler->handle($occurrenceId, $dto);

        $this->assertSame($updatedOccurrence, $result);
    }

    public function testHandleFallsBackToExistingStatusWhenStatusNotProvided(): void
    {
        $occurrenceId = 10;
        $eventId = 1;

        $dto = new UpsertEventOccurrenceDTO(
            event_id: $eventId,
            start_date: '2026-06-01 10:00:00',
            end_date: null,
            status: null,
            capacity: 50,
        );

        $existingOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $existingOccurrence->shouldReceive('getId')->andReturn($occurrenceId);
        $existingOccurrence->shouldReceive('getStatus')->andReturn(EventOccurrenceStatus::SOLD_OUT->name);

        $updatedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn($existingOccurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(
                $occurrenceId,
                [
                    EventOccurrenceDomainObjectAbstract::START_DATE => '2026-06-01 10:00:00',
                    EventOccurrenceDomainObjectAbstract::END_DATE => null,
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::SOLD_OUT->name,
                    EventOccurrenceDomainObjectAbstract::CAPACITY => 50,
                    EventOccurrenceDomainObjectAbstract::LABEL => null,
                    EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => true,
                ]
            )
            ->andReturn($updatedOccurrence);

        $result = $this->handler->handle($occurrenceId, $dto);

        $this->assertSame($updatedOccurrence, $result);
    }

    public function testHandleThrowsExceptionWhenOccurrenceNotFound(): void
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

        $this->occurrenceRepository
            ->shouldNotReceive('updateFromArray');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($occurrenceId, $dto);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
