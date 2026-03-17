<?php

namespace HiEvents\Services\Domain\Organizer;

use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Event\EventDeletionService;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class OrganizerDeletionService
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly EventRepositoryInterface     $eventRepository,
        private readonly EventDeletionService         $eventDeletionService,
        private readonly LoggerInterface              $logger,
        private readonly DatabaseManager              $databaseManager,
    )
    {
    }

    public function canDeleteOrganizer(int $organizerId, int $accountId): bool
    {
        $organizerCount = $this->organizerRepository->countWhere([
            'account_id' => $accountId,
        ]);

        if ($organizerCount <= 1) {
            return false;
        }

        $events = $this->eventRepository->findWhere([
            'organizer_id' => $organizerId,
        ]);

        foreach ($events as $event) {
            if (!$this->eventDeletionService->canDeleteEvent($event->getId())) {
                return false;
            }
        }

        return true;
    }

    public function getCannotDeleteReason(int $organizerId, int $accountId): ?string
    {
        $organizerCount = $this->organizerRepository->countWhere([
            'account_id' => $accountId,
        ]);

        if ($organizerCount <= 1) {
            return __('You cannot delete the last organizer on your account.');
        }

        $events = $this->eventRepository->findWhere([
            'organizer_id' => $organizerId,
        ]);

        foreach ($events as $event) {
            if (!$this->eventDeletionService->canDeleteEvent($event->getId())) {
                return __('This organizer has events with completed orders. Please cancel or refund all orders first.');
            }
        }

        return null;
    }

    /**
     * @throws CannotDeleteEntityException
     * @throws Throwable
     */
    public function deleteOrganizer(int $organizerId, int $accountId): void
    {
        $this->databaseManager->transaction(function () use ($organizerId, $accountId) {
            $reason = $this->getCannotDeleteReason($organizerId, $accountId);

            if ($reason !== null) {
                throw new CannotDeleteEntityException($reason);
            }

            $events = $this->eventRepository->findWhere([
                'organizer_id' => $organizerId,
            ]);

            foreach ($events as $event) {
                $this->eventRepository->deleteWhere([
                    'id' => $event->getId(),
                    'account_id' => $accountId,
                ]);
            }

            $this->organizerRepository->deleteWhere([
                'id' => $organizerId,
                'account_id' => $accountId,
            ]);
        });

        $this->logger->info('Organizer deleted', [
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
        ]);
    }
}
