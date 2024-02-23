<?php

namespace TicketKitten\Repository\Interfaces;

use TicketKitten\DomainObjects\PasswordResetTokenDomainObject;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<PasswordResetTokenDomainObject>
 */
interface PasswordResetTokenRepositoryInterface extends RepositoryInterface
{

}
