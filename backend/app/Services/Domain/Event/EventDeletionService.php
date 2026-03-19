<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class EventDeletionService
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface          $logger,
        private readonly DatabaseManager          $databaseManager,
    )
    {
    }

    public function canDeleteEvent(int $eventId): bool
    {
        return $this->orderRepository->countWhere([
                'event_id' => $eventId,
                'status' => OrderStatus::COMPLETED->name,
            ]) === 0;
    }

    /**
     * @throws CannotDeleteEntityException
     * @throws Throwable
     */
    public function deleteEvent(int $eventId, int $accountId): void
    {
        $this->databaseManager->transaction(function () use ($eventId, $accountId) {
            if (!$this->canDeleteEvent($eventId)) {
                throw new CannotDeleteEntityException(
                    __('This event cannot be deleted because it has completed orders. Please cancel or refund all orders first.')
                );
            }

            $this->eventRepository->deleteWhere([
                'id' => $eventId,
                'account_id' => $accountId,
            ]);
        });

        $this->logger->info('Event deleted', [
            'event_id' => $eventId,
            'account_id' => $accountId,
        ]);
    }
}
