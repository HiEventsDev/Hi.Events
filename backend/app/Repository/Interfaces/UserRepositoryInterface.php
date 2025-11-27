<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<UserDomainObject>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByIdAndAccountId(int $userId, int $accountId): UserDomainObject;

    public function findUsersByAccountId(int $accountId): ?Collection;

    public function getAllUsersWithAccounts(?string $search, int $perPage): LengthAwarePaginator;
}
