<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\OrderItem;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;

/**
 * @extends BaseRepository<OrderItemDomainObject>
 */
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

    public function getReservedQuantityForOccurrence(int $occurrenceId): int
    {
        return (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.event_occurrence_id', $occurrenceId)
            ->where('orders.status', OrderStatus::RESERVED->name)
            ->where('orders.reserved_until', '>', now())
            ->whereNull('orders.deleted_at')
            ->sum('order_items.quantity');
    }
}
