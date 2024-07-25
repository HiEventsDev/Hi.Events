<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Ticket\DTO\AvailableTicketQuantitiesDTO;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class FetchAvailableTicketQuantitiesService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Config          $config,
        private readonly Cache           $cache
    )
    {
    }

    /**
     * @param int $eventId
     * @return Collection<AvailableTicketQuantitiesDTO>
     */
    public function getAvailableTicketQuantities(int $eventId): Collection
    {
        if ($this->config->get('app.homepage_ticket_quantities_cache_ttl')) {
            $cachedData = $this->getDataFromCache($eventId);
            if ($cachedData) {
                return $cachedData;
            }
        }

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

        $result = collect($this->db->select($query, ['eventId' => $eventId, 'reserved' => $reserved]))->map(function ($row) {
            return AvailableTicketQuantitiesDTO::fromArray([
                'ticket_id' => $row->ticket_id,
                'price_id' => $row->price_id,
                'ticket_title' => $row->ticket_title,
                'price_label' => $row->price_label,
                'quantity_available' => $row->quantity_available,
            ]);
        });

        if ($this->config->get('app.homepage_ticket_quantities_cache_ttl')) {
            $this->cache->put(
                key: $this->getCacheKey($eventId),
                value: $result,
                ttl: $this->config->get('app.homepage_ticket_quantities_cache_ttl'),
            );
        }

        return $result;
    }

    private function getDataFromCache(int $eventId): ?Collection
    {
        return $this->cache->get($this->getCacheKey($eventId));
    }

    private function getCacheKey(int $eventId): string
    {
        return "event.$eventId.available_ticket_quantities";
    }
}
