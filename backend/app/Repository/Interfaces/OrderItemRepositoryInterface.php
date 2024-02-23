<?php

namespace TicketKitten\Repository\Interfaces;

use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<OrderItemDomainObject>
 */
interface OrderItemRepositoryInterface extends RepositoryInterface
{
}
