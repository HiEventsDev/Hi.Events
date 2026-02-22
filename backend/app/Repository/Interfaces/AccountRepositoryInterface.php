<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<AccountDomainObject>
 */
interface AccountRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId): AccountDomainObject;

    public function getAllAccountsWithCounts(?string $search, int $perPage): LengthAwarePaginator;

    public function getAccountWithDetails(int $accountId): Account;
}
