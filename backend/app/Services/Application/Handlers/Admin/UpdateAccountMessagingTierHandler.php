<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;

class UpdateAccountMessagingTierHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
    ) {
    }

    public function handle(int $accountId, int $tierId): AccountDomainObject
    {
        return $this->accountRepository->updateFromArray($accountId, [
            'account_messaging_tier_id' => $tierId,
        ]);
    }
}
