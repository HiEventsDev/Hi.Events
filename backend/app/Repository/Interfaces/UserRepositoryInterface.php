<?php

declare(strict_types=1);

namespace TicketKitten\Repository\Interfaces;

use Illuminate\Support\Collection;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<UserDomainObject>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    public function findUsersByAccountId(int $accountId): ?Collection;
}
