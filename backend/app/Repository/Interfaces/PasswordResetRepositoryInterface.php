<?php

namespace TicketKitten\Repository\Interfaces;

use TicketKitten\DomainObjects\PasswordResetDomainObject;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<PasswordResetDomainObject>
 */
interface PasswordResetRepositoryInterface extends RepositoryInterface
{

}
