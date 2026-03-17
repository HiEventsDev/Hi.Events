<?php

namespace Tests\Unit\Services\Application\Handlers\Event;

use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DeleteEventHandler;
use HiEvents\Services\Application\Handlers\Event\DTO\DeleteEventDTO;
use HiEvents\Services\Domain\Event\EventDeletionService;
use Illuminate\Database\DatabaseManager;
use Mockery as m;
use Tests\TestCase;
use Psr\Log\LoggerInterface;

class DeleteEventHandlerTest extends TestCase
{
    private EventDeletionService $eventDeletionService;
    private DeleteEventHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $eventRepository = m::mock(EventRepositoryInterface::class);
        $orderRepository = m::mock(OrderRepositoryInterface::class);
        $logger = m::mock(LoggerInterface::class);
        $databaseManager = m::mock(DatabaseManager::class);

        $databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->eventDeletionService = new EventDeletionService(
            $eventRepository,
            $orderRepository,
            $logger,
            $databaseManager,
        );

        $this->handler = new DeleteEventHandler($this->eventDeletionService);

        $orderRepository->shouldReceive('countWhere')
            ->byDefault()
            ->andReturn(0);

        $eventRepository->shouldReceive('deleteWhere')
            ->byDefault()
            ->andReturn(1);

        $logger->shouldReceive('info')
            ->byDefault();

        $this->orderRepository = $orderRepository;
        $this->eventRepository = $eventRepository;
    }

    private OrderRepositoryInterface $orderRepository;
    private EventRepositoryInterface $eventRepository;

    public function testDeleteEventSuccessfully(): void
    {
        $this->orderRepository->shouldReceive('countWhere')
            ->once()
            ->with(['event_id' => 1, 'status' => 'COMPLETED'])
            ->andReturn(0);

        $this->eventRepository->shouldReceive('deleteWhere')
            ->once()
            ->with(['id' => 1, 'account_id' => 10])
            ->andReturn(1);

        $dto = new DeleteEventDTO(eventId: 1, accountId: 10);

        $this->handler->handle($dto);

        $this->assertTrue(true);
    }

    public function testDeleteEventFailsWithCompletedOrders(): void
    {
        $this->orderRepository->shouldReceive('countWhere')
            ->once()
            ->with(['event_id' => 1, 'status' => 'COMPLETED'])
            ->andReturn(5);

        $dto = new DeleteEventDTO(eventId: 1, accountId: 10);

        $this->expectException(CannotDeleteEntityException::class);

        $this->handler->handle($dto);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
