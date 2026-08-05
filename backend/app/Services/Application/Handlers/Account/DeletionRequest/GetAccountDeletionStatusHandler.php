<?php

namespace HiEvents\Services\Application\Handlers\Account\DeletionRequest;

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
        $cannotDeleteReason = $this->accountDeletionService->getCannotDeleteReason($accountId);

        return new GetAccountDeletionStatusDTO(
            activeRequest: $activeRequest,
            canRequestDeletion: $cannotDeleteReason === null,
            cannotDeleteReason: $cannotDeleteReason,
            expectedOutcome: $this->accountDeletionService->determineOutcome($accountId)->name,
        );
    }
}
