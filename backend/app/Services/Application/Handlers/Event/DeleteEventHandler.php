<?php

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Services\Application\Handlers\Event\DTO\DeleteEventDTO;
use HiEvents\Services\Domain\Event\EventDeletionService;
use Throwable;

class DeleteEventHandler
{
    public function __construct(
        private readonly EventDeletionService $eventDeletionService,
    )
    {
    }

    /**
     * @throws CannotDeleteEntityException
     * @throws Throwable
     */
    public function handle(DeleteEventDTO $dto): void
    {
        $this->eventDeletionService->deleteEvent($dto->eventId, $dto->accountId);
    }
}
