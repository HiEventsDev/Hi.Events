<?php

namespace HiEvents\Services\Application\Handlers\Account\DeletionRequest;

use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;
use HiEvents\Exceptions\AccountDeletionRequestNotFoundException;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use Throwable;

class CancelAccountDeletionHandler
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
    ) {}

    /**
     * @throws AccountDeletionRequestNotFoundException
     * @throws Throwable
     */
    public function handle(int $accountId, int $cancelledByUserId): AccountDeletionRequestDomainObject
    {
        return $this->accountDeletionService->cancelDeletion($accountId, $cancelledByUserId);
    }
}
