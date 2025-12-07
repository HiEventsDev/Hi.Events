<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Order;
use HiEvents\Models\OrderItem;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\AccountDomainObject;

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
                        ), 'ilike', '%' . $params->query . '%')
                    ->orWhere(OrderDomainObjectAbstract::LAST_NAME, 'ilike', '%' . $params->query . '%')
                    ->orWhere(OrderDomainObjectAbstract::PUBLIC_ID, 'ilike', '%' . $params->query . '%')
                    ->orWhere(OrderDomainObjectAbstract::EMAIL, 'ilike', '%' . $params->query . '%');
            };
        }

        if (!empty($params->filter_fields)) {
            $this->applyFilterFields($params, OrderDomainObject::getAllowedFilterFields());
        }

        $this->model = $this->model->orderBy(
            column: $params->sort_by ?? OrderDomainObject::getDefaultSort(),
            direction: $params->sort_direction ?? 'desc',
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
                        ), 'ilike', '%' . $params->query . '%')
                    ->orWhere(OrderDomainObjectAbstract::LAST_NAME, 'ilike', '%' . $params->query . '%')
                    ->orWhere(OrderDomainObjectAbstract::PUBLIC_ID, 'ilike', '%' . $params->query . '%')
                    ->orWhere(OrderDomainObjectAbstract::EMAIL, 'ilike', '%' . $params->query . '%');
            };
        }

        if (!empty($params->filter_fields)) {
            $this->applyFilterFields($params, OrderDomainObject::getAllowedFilterFields());
        }

        $this->model = $this->model
            ->select('orders.*')
            ->join('events', 'orders.event_id', '=', 'events.id')
            ->where('events.organizer_id', $organizerId)
            ->where('events.account_id', $accountId);

        $this->model = $this->model->orderBy(
            column: $params->sort_by ? 'orders.' . $params->sort_by : 'orders.' . OrderDomainObject::getDefaultSort(),
            direction: $params->sort_direction ?? 'desc',
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

    /**
     * @param string $orderShortId
     * @return OrderDomainObject|null
     */
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

    public function findOrdersAssociatedWithProducts(int $eventId, array $productIds, array $orderStatuses): Collection
    {
        return $this->handleResults(
            $this->model
                ->whereHas('order_items', static function (Builder $query) use ($productIds) {
                    $query->whereIn('product_id', $productIds);
                })
                ->whereIn('status', $orderStatuses)
                ->where('event_id', $eventId)
                ->get()
        );
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
                $q->where(OrderDomainObjectAbstract::EMAIL, 'ilike', '%' . $search . '%')
                    ->orWhere(OrderDomainObjectAbstract::FIRST_NAME, 'ilike', '%' . $search . '%')
                    ->orWhere(OrderDomainObjectAbstract::LAST_NAME, 'ilike', '%' . $search . '%')
                    ->orWhere(OrderDomainObjectAbstract::PUBLIC_ID, 'ilike', '%' . $search . '%')
                    ->orWhere(OrderDomainObjectAbstract::SHORT_ID, 'ilike', '%' . $search . '%');
            });
        }

        $this->model = $this->model->where('orders.status', '!=', OrderStatus::RESERVED->name)
            ->where('orders.status', '!=', OrderStatus::ABANDONED->name);

        $allowedSortColumns = ['created_at', 'total_gross', 'email', 'first_name', 'last_name'];
        $sortColumn = in_array($sortBy, $allowedSortColumns, true) ? $sortBy : 'created_at';
        $sortDir = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $this->model = $this->model->orderBy('orders.' . $sortColumn, $sortDir);

        $this->loadRelation(new Relationship(EventDomainObject::class, nested: [
            new Relationship(AccountDomainObject::class, name: 'account')
        ], name: 'event'));

        return $this->paginate($perPage);
    }
}
