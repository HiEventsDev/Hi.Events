<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<TicketPriceDomainObject>
 */
interface TicketPriceRepositoryInterface extends RepositoryInterface
{
}
