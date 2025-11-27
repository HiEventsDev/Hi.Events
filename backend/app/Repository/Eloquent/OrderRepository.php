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
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
}
