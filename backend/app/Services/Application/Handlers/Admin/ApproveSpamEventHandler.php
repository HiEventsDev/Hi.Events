<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApproveSpamEventHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventSpamCheckRepositoryInterface $eventSpamCheckRepository,
        private readonly EventSpamCheckService $eventSpamCheckService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(int $eventId, int $reviewedByUserId): void
    {
        $this->databaseManager->transaction(function () use ($eventId, $reviewedByUserId) {
            $this->approveEvent($eventId, $reviewedByUserId);
        });
    }

    /**
     * @throws ResourceNotFoundException|ValidationException
     */
    private function approveEvent(int $eventId, int $reviewedByUserId): void
    {
        $flaggedCheck = $this->eventSpamCheckRepository->findFirstWhere([
            'event_id' => $eventId,
            'status' => EventSpamCheckStatus::FLAGGED->name,
        ]);

        if ($flaggedCheck === null) {
            throw new ResourceNotFoundException(__('No flagged spam check found for this event'));
        }

        $event = $this->eventRepository->findFirstWhere(['id' => $eventId]);

        $updated = $this->eventRepository->updateWhere(
            attributes: ['status' => EventStatus::LIVE->name],
            where: [
                'id' => $eventId,
                'status' => EventStatus::PENDING_MANUAL_REVIEW->name,
            ],
        );

        if ($event === null || $updated === 0) {
            throw ValidationException::withMessages([
                'status' => [__('Event must be pending manual review to be approved')],
            ]);
        }

        $this->eventSpamCheckRepository->updateWhere(
            attributes: [
                'status' => EventSpamCheckStatus::APPROVED->name,
                'content_hash' => $this->eventSpamCheckService->hashContent($event->getTitle(), $event->getDescription()),
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
