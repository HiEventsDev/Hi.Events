<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;

class UpdateAccountVerificationHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
    ) {}

    public function handle(int $accountId, bool $isManuallyVerified): AccountDomainObject
    {
        return $this->accountRepository->updateFromArray($accountId, [
            'is_manually_verified' => $isManuallyVerified,
        ]);
    }
}
