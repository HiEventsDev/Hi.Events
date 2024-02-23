<?php

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\Models\OrderItem;
use TicketKitten\Repository\Interfaces\OrderItemRepositoryInterface;

class OrderItemRepository extends BaseRepository implements OrderItemRepositoryInterface
{
    protected function getModel(): string
    {
        return OrderItem::class;
    }

    public function getDomainObject(): string
    {
        return OrderItemDomainObject::class;
    }
}
