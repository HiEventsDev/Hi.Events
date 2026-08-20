<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<AccountDeletionRequestDomainObject>
 */
interface AccountDeletionRequestRepositoryInterface extends RepositoryInterface
{
    public function findDueForReminder(int $daysBefore): Collection;

    public function getAllRequestsWithAccounts(?string $search, ?string $status, int $perPage): LengthAwarePaginator;
}
