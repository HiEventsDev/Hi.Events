<?php

namespace HiEvents\Services\Application\Handlers\Account\DeletionRequest;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Services\Application\Handlers\Account\DeletionRequest\DTO\AccountDeletionBlockingEventDTO;
use HiEvents\Services\Application\Handlers\Account\DeletionRequest\DTO\GetAccountDeletionStatusDTO;
use HiEvents\Services\Domain\Account\AccountDeletionService;

class GetAccountDeletionStatusHandler
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
    ) {}

    public function handle(int $accountId): GetAccountDeletionStatusDTO
    {
        $activeRequest = $this->accountDeletionService->findActiveRequest($accountId);
        $blockingEvents = $activeRequest === null
            ? $this->accountDeletionService->getBlockingEvents($accountId)
            : collect();
        $cannotDeleteReason = $this->accountDeletionService->describeCannotDeleteReason(
            hasActiveRequest: $activeRequest !== null,
            blockingEvents: $blockingEvents,
        );

        return new GetAccountDeletionStatusDTO(
            activeRequest: $activeRequest,
            canRequestDeletion: $cannotDeleteReason === null,
            cannotDeleteReason: $cannotDeleteReason,
            expectedOutcome: $this->accountDeletionService->determineOutcome($accountId)->name,
            blockingEvents: $blockingEvents
                ->map(fn (EventDomainObject $event) => new AccountDeletionBlockingEventDTO(
                    id: $event->getId(),
                    title: $event->getTitle(),
                ))
                ->values()
                ->all(),
        );
    }
}
