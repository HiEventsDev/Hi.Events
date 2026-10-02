<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\BoxOffice\Repository\DTO\BoxOfficeSalesCountDTO;
use HiEvents\Enterprise\BoxOffice\Repository\DTO\BoxOfficeSummaryRowDTO;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Order;
use HiEvents\Models\OrderItem;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<OrderDomainObject>
 */
class OrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [OrderDomainObjectAbstract::EVENT_ID, '=', $eventId],
            [OrderDomainObjectAbstract::STATUS, '!=', OrderStatus::RESERVED->name],
            [OrderDomainObjectAbstract::STATUS, '!=', OrderStatus::ABANDONED->name],
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(
                        DB::raw(
                            sprintf(
                                "(%s||' '||%s)",
                                OrderDomainObjectAbstract::FIRST_NAME,
                                OrderDomainObjectAbstract::LAST_NAME
                            )
                        ), 'ilike', '%'.$params->query.'%')
                    ->orWhere(OrderDomainObjectAbstract::LAST_NAME, 'ilike', '%'.$params->query.'%')
                    ->orWhere(OrderDomainObjectAbstract::PUBLIC_ID, 'ilike', '%'.$params->query.'%')
                    ->orWhere(OrderDomainObjectAbstract::EMAIL, 'ilike', '%'.$params->query.'%');
            };
        }

        if (! empty($params->filter_fields)) {
            $this->applyFilterFields($params, OrderDomainObject::getAllowedFilterFields());

            $occurrenceFilter = $params->filter_fields->firstWhere('field', 'event_occurrence_id');
            if ($occurrenceFilter) {
                $this->model = $this->model->whereHas('order_items', function (Builder $query) use ($occurrenceFilter) {
                    $query->where('order_items.event_occurrence_id', $occurrenceFilter->value);
                });
            }
        }

        $this->model = $this->model->orderBy(
            column: $this->validateSortColumn($params->sort_by, OrderDomainObject::class),
            direction: $this->validateSortDirection($params->sort_direction, OrderDomainObject::class),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function findByOrganizerId(int $organizerId, int $accountId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            ['orders.status', '!=', OrderStatus::RESERVED->name],
            ['orders.status', '!=', OrderStatus::ABANDONED->name],
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(
                        DB::raw(
                            sprintf(
                                "(%s||' '||%s)",
                                OrderDomainObjectAbstract::FIRST_NAME,
                                OrderDomainObjectAbstract::LAST_NAME
                            )
                        ), 'ilike', '%'.$params->query.'%')
                    ->orWhere(OrderDomainObjectAbstract::LAST_NAME, 'ilike', '%'.$params->query.'%')
                    ->orWhere(OrderDomainObjectAbstract::PUBLIC_ID, 'ilike', '%'.$params->query.'%')
                    ->orWhere(OrderDomainObjectAbstract::EMAIL, 'ilike', '%'.$params->query.'%');
            };
        }

        if (! empty($params->filter_fields)) {
            $this->applyFilterFields($params, OrderDomainObject::getAllowedFilterFields());
        }

        $this->model = $this->model
            ->select('orders.*')
            ->join('events', 'orders.event_id', '=', 'events.id')
            ->where('events.organizer_id', $organizerId)
            ->where('events.account_id', $accountId);

        $sortBy = $this->validateSortColumn($params->sort_by, OrderDomainObject::class);
        $this->model = $this->model->orderBy(
            column: 'orders.'.$sortBy,
            direction: $this->validateSortDirection($params->sort_direction, OrderDomainObject::class),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function getOrderItems(int $orderId)
    {
        return $this->handleResults(
            $this->model->find($orderId)->orderItems,
            OrderItemDomainObject::class
        );
    }

    public function getAttendees(int $orderId)
    {
        return $this->handleResults(
            $this->model->find($orderId)->attendees,
            AttendeeDomainObject::class
        );
    }

    public function addOrderItem(array $data): OrderItemDomainObject
    {
        $orderItem = $this->initModel(OrderItem::class)->create($data);

        return $this->handleSingleResult($orderItem, OrderItemDomainObject::class);
    }

    public function findByShortId(string $orderShortId): ?OrderDomainObject
    {
        return $this->findFirstByField('short_id', $orderShortId);
    }

    public function getDomainObject(): string
    {
        return OrderDomainObject::class;
    }

    protected function getModel(): string
    {
        return Order::class;
    }

    public function findOrdersAssociatedWithProducts(
        int $eventId,
        array $productIds,
        array $orderStatuses,
        ?int $eventOccurrenceId = null,
        ?array $eventOccurrenceIds = null,
    ): Collection {
        $query = $this->model
            ->whereHas('order_items', static function (Builder $query) use ($productIds, $eventOccurrenceId, $eventOccurrenceIds) {
                $query->whereIn('product_id', $productIds);
                if (! empty($eventOccurrenceIds)) {
                    $query->whereIn('order_items.event_occurrence_id', $eventOccurrenceIds);
                } elseif ($eventOccurrenceId !== null) {
                    $query->where('order_items.event_occurrence_id', $eventOccurrenceId);
                }
            })
            ->whereIn('status', $orderStatuses)
            ->where('event_id', $eventId);

        return $this->handleResults($query->get());
    }

    public function countOrdersAssociatedWithProducts(
        int $eventId,
        array $productIds,
        array $orderStatuses,
        ?int $eventOccurrenceId = null,
        ?array $eventOccurrenceIds = null,
    ): int {
        $count = $this->model
            ->whereHas('order_items', static function (Builder $query) use ($productIds, $eventOccurrenceId, $eventOccurrenceIds) {
                $query->whereIn('product_id', $productIds);
                if (! empty($eventOccurrenceIds)) {
                    $query->whereIn('order_items.event_occurrence_id', $eventOccurrenceIds);
                } elseif ($eventOccurrenceId !== null) {
                    $query->where('order_items.event_occurrence_id', $eventOccurrenceId);
                }
            })
            ->whereIn('status', $orderStatuses)
            ->where('event_id', $eventId)
            ->count();

        $this->resetModel();

        return $count;
    }

    public function countActivePromoCodeUsage(int $promoCodeId): int
    {
        $count = $this->model
            ->where('promo_code_id', $promoCodeId)
            ->where(static function (Builder $query) {
                $query->whereIn('status', [
                    OrderStatus::COMPLETED->name,
                    OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
                ])->orWhere(static function (Builder $reserved) {
                    $reserved->where('status', OrderStatus::RESERVED->name)
                        ->where('reserved_until', '>', now());
                });
            })
            ->count();

        $this->resetModel();

        return $count;
    }

    public function getAllOrdersForAdmin(
        ?string $search = null,
        int $perPage = 20,
        ?string $sortBy = 'created_at',
        ?string $sortDirection = 'desc'
    ): LengthAwarePaginator {
        $this->model = $this->model
            ->select('orders.*')
            ->join('events', 'orders.event_id', '=', 'events.id')
            ->join('accounts', 'events.account_id', '=', 'accounts.id');

        if ($search) {
            $this->model = $this->model->where(function ($q) use ($search) {
                $q->where(OrderDomainObjectAbstract::EMAIL, 'ilike', '%'.$search.'%')
                    ->orWhere(OrderDomainObjectAbstract::FIRST_NAME, 'ilike', '%'.$search.'%')
                    ->orWhere(OrderDomainObjectAbstract::LAST_NAME, 'ilike', '%'.$search.'%')
                    ->orWhere(OrderDomainObjectAbstract::PUBLIC_ID, 'ilike', '%'.$search.'%')
                    ->orWhere(OrderDomainObjectAbstract::SHORT_ID, 'ilike', '%'.$search.'%');
            });
        }

        $this->model = $this->model->where('orders.status', '!=', OrderStatus::RESERVED->name)
            ->where('orders.status', '!=', OrderStatus::ABANDONED->name);

        $allowedSortColumns = ['created_at', 'total_gross', 'email', 'first_name', 'last_name'];
        $sortColumn = in_array($sortBy, $allowedSortColumns, true) ? $sortBy : 'created_at';
        $sortDir = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $this->model = $this->model->orderBy('orders.'.$sortColumn, $sortDir);

        $this->loadRelation(new Relationship(EventDomainObject::class, nested: [
            new Relationship(AccountDomainObject::class, name: 'account'),
        ], name: 'event'));

        return $this->paginate($perPage);
    }

    public function hasCompletedPaidOrderForAccount(int $accountId): bool
    {
        $exists = $this->model
            ->join('events', 'orders.event_id', '=', 'events.id')
            ->join('stripe_payments', 'orders.id', '=', 'stripe_payments.order_id')
            ->where('events.account_id', $accountId)
            ->where('orders.payment_status', OrderPaymentStatus::PAYMENT_RECEIVED->name)
            ->whereNotNull('stripe_payments.payment_intent_id')
            ->exists();

        $this->resetModel();

        return $exists;
    }

    public function accountHasCompletedOrders(int $accountId): bool
    {
        return $this->runQuery(fn () => $this->model
            ->join('events', 'orders.event_id', '=', 'events.id')
            ->where('events.account_id', $accountId)
            ->where('orders.status', OrderStatus::COMPLETED->name)
            ->exists());
    }

    public function findByBoxOfficeId(int $boxOfficeId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [OrderDomainObjectAbstract::BOX_OFFICE_ID, '=', $boxOfficeId],
            static function (Builder $builder) {
                $builder
                    ->whereIn(OrderDomainObjectAbstract::STATUS, [OrderStatus::COMPLETED->name, OrderStatus::CANCELLED->name])
                    ->orWhere(static function (Builder $reserved) {
                        $reserved
                            ->where(OrderDomainObjectAbstract::STATUS, OrderStatus::RESERVED->name)
                            ->where(OrderDomainObjectAbstract::RESERVED_UNTIL, '>', now());
                    });
            },
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(
                        DB::raw(
                            sprintf(
                                "(%s||' '||%s)",
                                OrderDomainObjectAbstract::FIRST_NAME,
                                OrderDomainObjectAbstract::LAST_NAME
                            )
                        ), 'ilike', '%'.$params->query.'%')
                    ->orWhere(OrderDomainObjectAbstract::LAST_NAME, 'ilike', '%'.$params->query.'%')
                    ->orWhere(OrderDomainObjectAbstract::PUBLIC_ID, 'ilike', '%'.$params->query.'%')
                    ->orWhere(OrderDomainObjectAbstract::EMAIL, 'ilike', '%'.$params->query.'%');
            };
        }

        $this->model = $this->model->orderBy(OrderDomainObjectAbstract::CREATED_AT, 'desc');

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function getBoxOfficeSalesCountsByIds(array $boxOfficeIds): Collection
    {
        if ($boxOfficeIds === []) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($boxOfficeIds), '?'));

        $rows = $this->db->select(
            <<<SQL
                SELECT box_office_id, COUNT(*) AS sales_count, COALESCE(SUM(total_gross), 0) AS gross_sales
                FROM orders
                WHERE box_office_id IN ($placeholders)
                  AND status = ?
                  AND deleted_at IS NULL
                GROUP BY box_office_id
            SQL,
            array_merge($boxOfficeIds, [OrderStatus::COMPLETED->name]),
        );

        return collect($rows)->map(static fn ($row) => new BoxOfficeSalesCountDTO(
            boxOfficeId: (int) $row->box_office_id,
            salesCount: (int) $row->sales_count,
            grossSales: (float) $row->gross_sales,
        ));
    }

    public function getBoxOfficeSummary(int $boxOfficeId, string $timezone, ?string $from, ?string $to): Collection
    {
        $bindings = [
            'timezone' => $timezone,
            'box_office_id' => $boxOfficeId,
            'status' => OrderStatus::COMPLETED->name,
        ];
        $rangeClause = '';

        if ($from !== null) {
            $rangeClause .= ' AND created_at >= :from';
            $bindings['from'] = $from;
        }

        if ($to !== null) {
            $rangeClause .= ' AND created_at <= :to';
            $bindings['to'] = $to;
        }

        $rows = $this->db->select(
            <<<SQL
                SELECT
                    box_office_tender AS tender,
                    COALESCE(box_office_operator_name, '') AS operator_name,
                    (created_at AT TIME ZONE 'UTC' AT TIME ZONE :timezone)::date AS day,
                    COUNT(*) AS orders,
                    COALESCE(SUM(total_gross), 0) AS gross,
                    COALESCE(SUM(total_refunded), 0) AS refunded
                FROM orders
                WHERE box_office_id = :box_office_id
                  AND status = :status
                  AND deleted_at IS NULL
                  $rangeClause
                GROUP BY box_office_tender, box_office_operator_name, day
                ORDER BY day
            SQL,
            $bindings,
        );

        return collect($rows)->map(static fn ($row) => new BoxOfficeSummaryRowDTO(
            tender: (string) $row->tender,
            operatorName: (string) $row->operator_name,
            day: (string) $row->day,
            orders: (int) $row->orders,
            gross: (float) $row->gross,
            refunded: (float) $row->refunded,
        ));
    }
}
