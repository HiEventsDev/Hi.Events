<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConfirmSpamEventHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventSpamCheckRepositoryInterface $eventSpamCheckRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(int $eventId, int $reviewedByUserId): void
    {
        $this->databaseManager->transaction(function () use ($eventId, $reviewedByUserId) {
            $this->confirmSpam($eventId, $reviewedByUserId);
        });
    }

    /**
     * @throws ResourceNotFoundException|ValidationException
     */
    private function confirmSpam(int $eventId, int $reviewedByUserId): void
    {
        $flaggedCheck = $this->eventSpamCheckRepository->findFirstWhere([
            'event_id' => $eventId,
            'status' => EventSpamCheckStatus::FLAGGED->name,
        ]);

        if ($flaggedCheck === null) {
            throw new ResourceNotFoundException(__('No flagged spam check found for this event'));
        }

        $event = $this->eventRepository->findFirstWhere(['id' => $eventId]);

        if ($event === null || $event->getStatus() !== EventStatus::PENDING_MANUAL_REVIEW->name) {
            throw ValidationException::withMessages([
                'status' => [__('Event must be pending manual review to be confirmed as spam')],
            ]);
        }

        $this->eventSpamCheckRepository->updateWhere(
            attributes: [
                'status' => EventSpamCheckStatus::CONFIRMED_SPAM->name,
                'reviewed_by_user_id' => $reviewedByUserId,
                'reviewed_at' => now(),
            ],
            where: [
                'event_id' => $eventId,
                'status' => EventSpamCheckStatus::FLAGGED->name,
            ],
        );
    }
}
