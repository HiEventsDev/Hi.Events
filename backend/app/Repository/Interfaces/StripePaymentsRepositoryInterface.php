<?php

namespace TicketKitten\Repository\Interfaces;

use TicketKitten\DomainObjects\StripePaymentDomainObject;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<StripePaymentDomainObject>
 */
interface StripePaymentsRepositoryInterface extends RepositoryInterface
{
}
