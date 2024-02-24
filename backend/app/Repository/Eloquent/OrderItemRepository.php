<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Models\OrderItem;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;

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
