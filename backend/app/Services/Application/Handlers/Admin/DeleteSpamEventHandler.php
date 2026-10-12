<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\Enums\AccountDeletionInitiator;
use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\DeleteSpamEventDTO;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use HiEvents\Services\Domain\Event\EventDeletionService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class DeleteSpamEventHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventSpamCheckRepositoryInterface $eventSpamCheckRepository,
        private readonly EventDeletionService $eventDeletionService,
        private readonly AccountDeletionService $accountDeletionService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws CannotDeleteEntityException
     * @throws Throwable
     */
    public function handle(DeleteSpamEventDTO $dto): void
    {
        $event = $this->eventRepository->findFirstWhere(['id' => $dto->eventId]);

        $flaggedCheck = $this->eventSpamCheckRepository->findFirstWhere([
            'event_id' => $dto->eventId,
            'status' => EventSpamCheckStatus::FLAGGED->name,
        ]);

        if ($event === null || $flaggedCheck === null || $event->getStatus() !== EventStatus::PENDING_MANUAL_REVIEW->name) {
            throw new ResourceNotFoundException(__('No flagged event awaiting review was found'));
        }

        $this->databaseManager->transaction(function () use ($dto, $event): void {
            $this->eventSpamCheckRepository->updateWhere(
                attributes: [
                    'status' => EventSpamCheckStatus::CONFIRMED_SPAM->name,
                    'reviewed_by_user_id' => $dto->reviewedByUserId,
                    'reviewed_at' => now(),
                ],
                where: [
                    'event_id' => $dto->eventId,
                    'status' => EventSpamCheckStatus::FLAGGED->name,
                ],
            );

            $this->eventDeletionService->deleteEvent($dto->eventId, $event->getAccountId());

            if ($dto->deleteAccount) {
                $this->accountDeletionService->requestDeletion(
                    accountId: $event->getAccountId(),
                    requestedByUserId: $dto->reviewedByUserId,
                    initiator: AccountDeletionInitiator::ADMIN,
                    reason: __('Spam'),
                    executeImmediately: true,
                );
            }
        });
    }
}
