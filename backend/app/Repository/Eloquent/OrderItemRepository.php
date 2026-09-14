<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\OrderItem;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

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

    public function getReservedTicketQuantityForOccurrence(int $occurrenceId): int
    {
        return $this->runQuery(fn () => (int) $this->reservedItemsQuery()
            ->where('order_items.event_occurrence_id', $occurrenceId)
            ->where('order_items.product_type', ProductType::TICKET->name)
            ->sum('order_items.quantity'));
    }

    public function getReservedQuantitiesByPrice(int $eventId, ?int $occurrenceId = null): array
    {
        return $this->runQuery(fn () => $this->reservedItemsQuery()
            ->where('orders.event_id', $eventId)
            ->when($occurrenceId !== null, fn (Builder $query) => $query->where('order_items.event_occurrence_id', $occurrenceId))
            ->groupBy('order_items.product_price_id')
            ->selectRaw('order_items.product_price_id, SUM(order_items.quantity) AS quantity')
            ->pluck('quantity', 'product_price_id')
            ->map(fn ($quantity) => (int) $quantity)
            ->all());
    }

    public function getSoldQuantitiesByPriceForOccurrence(int $occurrenceId): array
    {
        return $this->runQuery(fn () => $this->soldGeneralItemsQuery()
            ->where('order_items.event_occurrence_id', $occurrenceId)
            ->groupBy('order_items.product_price_id')
            ->selectRaw('order_items.product_price_id, SUM(order_items.quantity) AS quantity')
            ->pluck('quantity', 'product_price_id')
            ->map(fn ($quantity) => (int) $quantity)
            ->all());
    }

    public function getMaxSoldPerOccurrenceByPrice(array $productPriceIds): array
    {
        return $this->runQuery(fn () => $this->soldGeneralItemsQuery()
            ->whereIn('order_items.product_price_id', $productPriceIds)
            ->whereNotNull('order_items.event_occurrence_id')
            ->groupBy('order_items.product_price_id', 'order_items.event_occurrence_id')
            ->selectRaw('order_items.product_price_id, SUM(order_items.quantity) AS quantity')
            ->get()
            ->groupBy('product_price_id')
            ->map(fn ($rows) => (int) $rows->max('quantity'))
            ->all());
    }

    private function reservedItemsQuery(): Builder
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::RESERVED->name)
            ->where('orders.reserved_until', '>', now())
            ->whereNull('orders.deleted_at');
    }

    private function soldGeneralItemsQuery(): Builder
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_type', ProductType::GENERAL->name)
            ->whereIn('orders.status', [OrderStatus::COMPLETED->name, OrderStatus::AWAITING_OFFLINE_PAYMENT->name])
            ->whereNull('orders.deleted_at');
    }
}
