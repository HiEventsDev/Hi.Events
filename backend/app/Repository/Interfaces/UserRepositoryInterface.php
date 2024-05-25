<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use Illuminate\Support\Collection;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<UserDomainObject>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByIdAndAccountId(int $userId, int $accountId): UserDomainObject;

    public function findUsersByAccountId(int $accountId): ?Collection;
}
