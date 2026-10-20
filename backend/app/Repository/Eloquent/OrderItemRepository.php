<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\ProductPurchaseStatus;
use HiEvents\Models\OrderItem;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseDTO;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseFilterDTO;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseSummaryDTO;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\LazyCollection;

/**
 * @extends BaseRepository<OrderItemDomainObject>
 */
class OrderItemRepository extends BaseRepository implements OrderItemRepositoryInterface
{
    private const PRODUCT_PURCHASE_ORDERING = 'ORDER BY order_created_at DESC, order_id DESC, product_id, occurrence_start_date NULLS LAST, product_price_id';

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

    public function getReservedQuantitiesByPrice(array $eventIds, ?int $occurrenceId = null): array
    {
        return $this->runQuery(fn () => $this->reservedItemsQuery()
            ->whereIn('orders.event_id', $eventIds)
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

    public function getProductIdsInLiveReservations(array $productIds): array
    {
        return $this->runQuery(fn () => $this->reservedItemsQuery()
            ->whereIn('order_items.product_id', $productIds)
            ->distinct()
            ->pluck('order_items.product_id')
            ->map(fn ($productId) => (int) $productId)
            ->all());
    }

    public function findProductPurchases(ProductPurchaseFilterDTO $filter, int $page, int $perPage): LengthAwarePaginator
    {
        [$sql, $bindings] = $this->filteredProductPurchasesQuery($filter);

        $total = (int) $this->db->selectOne("SELECT COUNT(*) AS total FROM ($sql) AS filtered_purchases", $bindings)->total;

        $rows = $this->db->select(
            $sql.' '.self::PRODUCT_PURCHASE_ORDERING.' LIMIT :limit OFFSET :offset',
            [...$bindings, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        return new Paginator(
            items: collect($rows)->map(fn ($row) => $this->toProductPurchaseDTO($row)),
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function getAllProductPurchases(ProductPurchaseFilterDTO $filter): LazyCollection
    {
        [$sql, $bindings] = $this->filteredProductPurchasesQuery($filter);

        return LazyCollection::make(fn () => $this->db->cursor($sql.' '.self::PRODUCT_PURCHASE_ORDERING, $bindings))
            ->map(fn ($row) => $this->toProductPurchaseDTO($row));
    }

    public function getProductPurchaseSummary(ProductPurchaseFilterDTO $filter): ProductPurchaseSummaryDTO
    {
        [$sql, $bindings] = $this->productPurchasesQuery($filter);
        $bindings['refunded'] = OrderRefundStatus::REFUNDED->name;
        $bindings['partially_refunded'] = OrderRefundStatus::PARTIALLY_REFUNDED->name;

        $row = $this->db->selectOne(
            <<<SQL
                SELECT
                    COALESCE(SUM(sold_quantity), 0) AS sold_quantity,
                    COALESCE(SUM(awaiting_payment_quantity), 0) AS awaiting_payment_quantity,
                    COALESCE(SUM(cancelled_quantity), 0) AS cancelled_quantity,
                    COUNT(DISTINCT COALESCE(LOWER(email), 'order:' || order_id))
                        FILTER (WHERE sold_quantity + awaiting_payment_quantity > 0) AS buyer_count,
                    COALESCE(SUM(line_total) FILTER (WHERE order_status = :order_completed), 0) AS gross_sales,
                    COUNT(DISTINCT order_id)
                        FILTER (WHERE refund_status IN (:refunded, :partially_refunded) AND sold_quantity + awaiting_payment_quantity > 0) AS refunded_order_count
                FROM ($sql) AS purchases
            SQL,
            $bindings,
        );

        return new ProductPurchaseSummaryDTO(
            soldQuantity: (int) $row->sold_quantity,
            awaitingPaymentQuantity: (int) $row->awaiting_payment_quantity,
            cancelledQuantity: (int) $row->cancelled_quantity,
            buyerCount: (int) $row->buyer_count,
            grossSales: round((float) $row->gross_sales, 2),
            refundedOrderCount: (int) $row->refunded_order_count,
        );
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filteredProductPurchasesQuery(ProductPurchaseFilterDTO $filter): array
    {
        [$sql, $bindings] = $this->productPurchasesQuery($filter);
        $conditions = [];

        if ($filter->statuses !== []) {
            $placeholders = [];
            foreach (array_values($filter->statuses) as $index => $status) {
                $placeholders[] = ':status_'.$index;
                $bindings['status_'.$index] = $status;
            }
            $conditions[] = 'status IN ('.implode(', ', $placeholders).')';
        }

        if ($filter->refundStatuses !== []) {
            $placeholders = [];
            foreach (array_values($filter->refundStatuses) as $index => $refundStatus) {
                $placeholders[] = ':refund_status_'.$index;
                $bindings['refund_status_'.$index] = $refundStatus;
            }
            $conditions[] = 'refund_status IN ('.implode(', ', $placeholders).')';
        }

        if ($filter->query !== null && trim($filter->query) !== '') {
            $bindings['search'] = '%'.trim($filter->query).'%';
            $conditions[] = "(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '') ILIKE :search"
                .' OR email ILIKE :search OR order_public_id ILIKE :search)';
        }

        $where = $conditions === [] ? '' : 'WHERE '.implode(' AND ', $conditions);

        return ["SELECT * FROM ($sql) AS purchases $where", $bindings];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function productPurchasesQuery(ProductPurchaseFilterDTO $filter): array
    {
        $bindings = [
            'event_id' => $filter->eventId,
            'order_completed' => OrderStatus::COMPLETED->name,
            'order_awaiting_offline_payment' => OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
            'order_cancelled' => OrderStatus::CANCELLED->name,
            'attendee_active' => AttendeeStatus::ACTIVE->name,
            'attendee_awaiting_payment' => AttendeeStatus::AWAITING_PAYMENT->name,
            'ticket_product_type' => ProductType::TICKET->name,
            'purchase_sold' => ProductPurchaseStatus::SOLD->name,
            'purchase_awaiting_payment' => ProductPurchaseStatus::AWAITING_PAYMENT->name,
            'purchase_cancelled' => ProductPurchaseStatus::CANCELLED->name,
        ];
        $attendeeFilters = '';
        $itemFilters = '';

        if ($filter->productId !== null) {
            $bindings['product_id'] = $filter->productId;
            $attendeeFilters .= ' AND a.product_id = :product_id';
            $itemFilters .= ' AND oi.product_id = :product_id';
        }

        if ($filter->eventOccurrenceId !== null) {
            $bindings['event_occurrence_id'] = $filter->eventOccurrenceId;
            $attendeeFilters .= ' AND a.event_occurrence_id = :event_occurrence_id';
            $itemFilters .= ' AND oi.event_occurrence_id = :event_occurrence_id';
        }

        $sql = <<<SQL
            WITH ticket_lines AS (
                SELECT
                    a.order_id,
                    a.product_id,
                    a.product_price_id,
                    a.event_occurrence_id,
                    COUNT(*) AS quantity,
                    COUNT(*) FILTER (WHERE o.status = :order_completed AND a.status = :attendee_active) AS sold_quantity,
                    COUNT(*) FILTER (
                        WHERE o.status = :order_awaiting_offline_payment AND a.status IN (:attendee_active, :attendee_awaiting_payment)
                    ) AS awaiting_payment_quantity
                FROM attendees a
                JOIN orders o ON o.id = a.order_id
                WHERE o.event_id = :event_id
                    AND o.status IN (:order_completed, :order_awaiting_offline_payment, :order_cancelled)
                    AND o.deleted_at IS NULL
                    AND a.deleted_at IS NULL
                    $attendeeFilters
                GROUP BY a.order_id, a.product_id, a.product_price_id, a.event_occurrence_id
            ),
            general_lines AS (
                SELECT
                    oi.order_id,
                    oi.product_id,
                    oi.product_price_id,
                    oi.event_occurrence_id,
                    SUM(oi.quantity) AS quantity,
                    CASE WHEN o.status = :order_completed THEN SUM(oi.quantity) ELSE 0 END AS sold_quantity,
                    CASE WHEN o.status = :order_awaiting_offline_payment THEN SUM(oi.quantity) ELSE 0 END AS awaiting_payment_quantity
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                WHERE o.event_id = :event_id
                    AND o.status IN (:order_completed, :order_awaiting_offline_payment, :order_cancelled)
                    AND o.deleted_at IS NULL
                    AND oi.deleted_at IS NULL
                    AND oi.product_type != :ticket_product_type
                    $itemFilters
                GROUP BY oi.order_id, o.status, oi.product_id, oi.product_price_id, oi.event_occurrence_id
            ),
            lines AS (
                SELECT * FROM ticket_lines
                UNION ALL
                SELECT * FROM general_lines
            ),
            item_totals AS (
                SELECT
                    oi.order_id,
                    oi.product_id,
                    oi.product_price_id,
                    oi.event_occurrence_id,
                    SUM(oi.total_gross) AS line_total
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                WHERE o.event_id = :event_id
                    AND o.deleted_at IS NULL
                    AND oi.deleted_at IS NULL
                    $itemFilters
                GROUP BY oi.order_id, oi.product_id, oi.product_price_id, oi.event_occurrence_id
            )
            SELECT
                l.order_id,
                o.public_id AS order_public_id,
                o.status AS order_status,
                o.refund_status,
                o.created_at AS order_created_at,
                o.currency,
                o.first_name,
                o.last_name,
                o.email,
                l.product_id,
                p.title AS product_title,
                l.product_price_id,
                pp.label AS price_label,
                l.event_occurrence_id,
                eo.start_date AS occurrence_start_date,
                l.sold_quantity,
                l.awaiting_payment_quantity,
                l.quantity - l.sold_quantity - l.awaiting_payment_quantity AS cancelled_quantity,
                it.line_total,
                CASE
                    WHEN l.sold_quantity > 0 THEN :purchase_sold
                    WHEN l.awaiting_payment_quantity > 0 THEN :purchase_awaiting_payment
                    ELSE :purchase_cancelled
                END AS status
            FROM lines l
            JOIN orders o ON o.id = l.order_id
            JOIN products p ON p.id = l.product_id
            LEFT JOIN product_prices pp ON pp.id = l.product_price_id
            LEFT JOIN event_occurrences eo ON eo.id = l.event_occurrence_id
            LEFT JOIN item_totals it
                ON it.order_id = l.order_id
                AND it.product_id = l.product_id
                AND it.product_price_id IS NOT DISTINCT FROM l.product_price_id
                AND it.event_occurrence_id IS NOT DISTINCT FROM l.event_occurrence_id
        SQL;

        return [$sql, $bindings];
    }

    private function toProductPurchaseDTO(object $row): ProductPurchaseDTO
    {
        return new ProductPurchaseDTO(
            orderId: (int) $row->order_id,
            orderPublicId: (string) $row->order_public_id,
            orderStatus: (string) $row->order_status,
            refundStatus: $row->refund_status,
            orderCreatedAt: (string) $row->order_created_at,
            currency: (string) $row->currency,
            firstName: $row->first_name,
            lastName: $row->last_name,
            email: $row->email,
            productTitle: (string) $row->product_title,
            productPriceId: $row->product_price_id !== null ? (int) $row->product_price_id : null,
            priceLabel: $row->price_label,
            eventOccurrenceId: $row->event_occurrence_id !== null ? (int) $row->event_occurrence_id : null,
            occurrenceStartDate: $row->occurrence_start_date,
            soldQuantity: (int) $row->sold_quantity,
            awaitingPaymentQuantity: (int) $row->awaiting_payment_quantity,
            cancelledQuantity: (int) $row->cancelled_quantity,
            lineTotal: $row->line_total !== null ? round((float) $row->line_total, 2) : null,
            status: (string) $row->status,
        );
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
