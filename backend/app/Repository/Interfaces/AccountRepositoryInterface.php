<?php

declare(strict_types=1);

namespace TicketKitten\Repository\Interfaces;

use TicketKitten\DomainObjects\AccountDomainObject;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<AccountDomainObject>
 */
interface AccountRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId): AccountDomainObject;
}
