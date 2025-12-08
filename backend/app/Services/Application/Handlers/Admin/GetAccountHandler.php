<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Repository\Interfaces\AccountRepositoryInterface;

class GetAccountHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
    )
    {
    }

    public function handle(int $accountId)
    {
        return $this->accountRepository->getAccountWithDetails($accountId);
    }
}
