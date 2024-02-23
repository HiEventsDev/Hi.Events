<?php

declare(strict_types=1);

namespace TicketKitten\Repository\Interfaces;

use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<TicketPriceDomainObject>
 */
interface TicketPriceRepositoryInterface extends RepositoryInterface
{
}
