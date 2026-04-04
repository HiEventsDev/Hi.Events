<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DeleteEventOccurrenceHandler;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use HiEvents\Exceptions\ResourceNotFoundException;
use Tests\TestCase;

class DeleteEventOccurrenceHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;
    private OrderItemRepositoryInterface|MockInterface $orderItemRepository;
    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;
    private DatabaseManager|MockInterface $databaseManager;
    private DeleteEventOccurrenceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->handler = new DeleteEventOccurrenceHandler(
            $this->occurrenceRepository,
            $this->orderItemRepository,
            $this->attendeeRepository,
            $this->databaseManager,
        );
    }

    public function testHandleSuccessfullyDeletesOccurrenceWithNoOrders(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn($occurrence);

        $this->orderItemRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['event_occurrence_id' => $occurrenceId])
            ->andReturn(0);

        $this->attendeeRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['event_occurrence_id' => $occurrenceId])
            ->andReturn(0);

        $this->occurrenceRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
            ]);

        $this->handler->handle($eventId, $occurrenceId);

        $this->assertTrue(true);
    }

    public function testHandleThrowsValidationExceptionWhenOccurrenceHasOrders(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn($occurrence);

        $this->orderItemRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['event_occurrence_id' => $occurrenceId])
            ->andReturn(5);

        $this->occurrenceRepository
            ->shouldNotReceive('deleteWhere');

        $this->expectException(ValidationException::class);

        $this->handler->handle($eventId, $occurrenceId);
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

        $this->orderItemRepository
            ->shouldNotReceive('countWhere');

        $this->occurrenceRepository
            ->shouldNotReceive('deleteWhere');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($eventId, $occurrenceId);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
