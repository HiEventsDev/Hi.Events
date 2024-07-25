<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Event;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EventRepository extends BaseRepository implements EventRepositoryInterface
{
    protected function getModel(): string
    {
        return Event::class;
    }

    public function getDomainObject(): string
    {
        return EventDomainObject::class;
    }

    public function findEvents(array $where, QueryParamsDTO $params): LengthAwarePaginator
    {
        if (!empty($params->query)) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(EventDomainObjectAbstract::TITLE, 'ilike', '%' . $params->query . '%');
            };
        }

        $this->model = $this->model->orderBy(
            $params->sort_by ?? EventDomainObject::getDefaultSort(),
            $params->sort_direction ?? EventDomainObject::getDefaultSortDirection(),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function getAvailableTicketQuantities(int $eventId): Collection
    {
        $reserved = OrderStatus::RESERVED->name;

        $query = <<<SQL
        WITH reserved_quantities AS (
            SELECT order_items.ticket_id,
                   order_items.ticket_price_id,
                   SUM(order_items.quantity) AS quantity_reserved
            FROM orders
            INNER JOIN order_items ON order_items.order_id = orders.id
            WHERE orders.event_id = :eventId
              AND orders.status = :reserved
              AND orders.reserved_until > NOW()
              AND orders.deleted_at IS NULL
            GROUP BY order_items.ticket_id, order_items.ticket_price_id
        ),
        capacity_reserved AS (
            SELECT ticket_capacity_assignments.ticket_id,
                   ticket_capacity_assignments.capacity_assignment_id,
                   SUM(order_items.quantity) AS quantity_reserved
            FROM orders
            INNER JOIN order_items ON order_items.order_id = orders.id
            INNER JOIN ticket_capacity_assignments ON order_items.ticket_id = ticket_capacity_assignments.ticket_id
            WHERE orders.event_id = :eventId
              AND orders.status = :reserved
              AND orders.reserved_until > NOW()
              AND orders.deleted_at IS NULL
            GROUP BY ticket_capacity_assignments.ticket_id, ticket_capacity_assignments.capacity_assignment_id
        ),
        min_capacity AS (
            SELECT ticket_capacity_assignments.ticket_id,
                   MIN(capacity_assignments.capacity - capacity_assignments.used_capacity - COALESCE(capacity_reserved.quantity_reserved, 0)) AS remaining_capacity
            FROM ticket_capacity_assignments
            INNER JOIN capacity_assignments ON ticket_capacity_assignments.capacity_assignment_id = capacity_assignments.id
            LEFT JOIN capacity_reserved ON ticket_capacity_assignments.ticket_id = capacity_reserved.ticket_id
                                       AND ticket_capacity_assignments.capacity_assignment_id = capacity_reserved.capacity_assignment_id
            WHERE capacity_assignments.deleted_at IS NULL
            GROUP BY ticket_capacity_assignments.ticket_id
        )
        SELECT
            tickets.id AS ticket_id,
            ticket_prices.id AS price_id,
            tickets.title AS ticket_title,
            ticket_prices.label AS price_label,
            LEAST(
                ticket_prices.initial_quantity_available - ticket_prices.quantity_sold - COALESCE(reserved_quantities.quantity_reserved, 0),
                COALESCE(min_capacity.remaining_capacity, ticket_prices.initial_quantity_available - ticket_prices.quantity_sold - COALESCE(reserved_quantities.quantity_reserved, 0))
            ) AS quantity_available
        FROM ticket_prices
        INNER JOIN tickets ON ticket_prices.ticket_id = tickets.id
        LEFT JOIN reserved_quantities ON ticket_prices.id = reserved_quantities.ticket_price_id
        LEFT JOIN min_capacity ON tickets.id = min_capacity.ticket_id
        WHERE tickets.event_id = :eventId
          AND tickets.deleted_at IS NULL
          AND ticket_prices.deleted_at IS NULL
        ORDER BY ticket_prices.id;
SQL;
        return collect($this->db->select($query, ['eventId' => $eventId, 'reserved' => $reserved]));
    }
}
