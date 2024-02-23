<?php

declare(strict_types=1);

namespace TicketKitten\Repository\Interfaces;

use TicketKitten\DomainObjects\OrganizerDomainObject;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<OrganizerDomainObject>
 */
interface OrganizerRepositoryInterface extends RepositoryInterface
{
}
