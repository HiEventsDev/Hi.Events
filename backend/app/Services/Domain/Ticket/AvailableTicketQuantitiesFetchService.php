<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\Constants;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\DomainObjects\Status\CapacityAssignmentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Services\Domain\Ticket\DTO\AvailableTicketQuantitiesDTO;
use HiEvents\Services\Domain\Ticket\DTO\AvailableTicketQuantitiesResponseDTO;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class AvailableTicketQuantitiesFetchService
{
    public function __construct(
        private readonly DatabaseManager                       $db,
        private readonly Config                                $config,
        private readonly Cache                                 $cache,
        private readonly CapacityAssignmentRepositoryInterface $capacityAssignmentRepository,
    )
    {
    }

    public function getAvailableTicketQuantities(int $eventId, bool $ignoreCache = false): AvailableTicketQuantitiesResponseDTO
    {
        if (!$ignoreCache && $this->config->get('app.homepage_ticket_quantities_cache_ttl')) {
            $cachedData = $this->getDataFromCache($eventId);
            if ($cachedData) {
                return $cachedData;
            }
        }

        $capacities = $this->capacityAssignmentRepository
            ->loadRelation(TicketDomainObject::class)
            ->findWhere([
                'event_id' => $eventId,
                'applies_to' => CapacityAssignmentAppliesTo::TICKETS->name,
                'status' => CapacityAssignmentStatus::ACTIVE->name,
            ]);

        $reservedTicketQuantities = $this->fetchReservedTicketQuantities($eventId);
        $ticketCapacities = $this->calculateTicketCapacities($capacities);

        $quantities = $reservedTicketQuantities->map(function (AvailableTicketQuantitiesDTO $dto) use ($ticketCapacities) {
            $ticketId = $dto->ticket_id;
            if (isset($ticketCapacities[$ticketId])) {
                $dto->quantity_available = min(array_merge([$dto->quantity_available], $ticketCapacities[$ticketId]->map->getAvailableCapacity()->toArray()));
                $dto->capacities = $ticketCapacities[$ticketId];
            }

            return $dto;
        });

        $finalData = new AvailableTicketQuantitiesResponseDTO(
            ticketQuantities: $quantities,
            capacities: $capacities
        );

        if (!$ignoreCache && $this->config->get('app.homepage_ticket_quantities_cache_ttl')) {
            $this->cache->put($this->getCacheKey($eventId), $finalData, $this->config->get('app.homepage_ticket_quantities_cache_ttl'));
        }

        return $finalData;
    }

    private function fetchReservedTicketQuantities(int $eventId): Collection
    {
        $result = $this->db->select(<<<SQL
        WITH reserved_quantities AS (
            SELECT
                tickets.id AS ticket_id,
                ticket_prices.id AS ticket_price_id,
                SUM(
                    CASE
                        WHEN orders.status = :reserved
                             AND orders.reserved_until > NOW()
                             AND orders.deleted_at IS NULL
                        THEN order_items.quantity
                        ELSE 0
                    END
                ) AS quantity_reserved
            FROM tickets
            JOIN ticket_prices ON tickets.id = ticket_prices.ticket_id
            LEFT JOIN order_items ON order_items.ticket_id = tickets.id
                AND order_items.ticket_price_id = ticket_prices.id
            LEFT JOIN orders ON orders.id = order_items.order_id
                AND orders.event_id = tickets.event_id
                AND orders.deleted_at IS NULL
            WHERE
                tickets.event_id = :eventId
                AND tickets.deleted_at IS NULL
                AND ticket_prices.deleted_at IS NULL
            GROUP BY tickets.id, ticket_prices.id
        )
        SELECT
            tickets.id AS ticket_id,
            ticket_prices.id AS ticket_price_id,
            tickets.title AS ticket_title,
            ticket_prices.label AS price_label,
            ticket_prices.initial_quantity_available,
            ticket_prices.quantity_sold,
            COALESCE(
                ticket_prices.initial_quantity_available
                - ticket_prices.quantity_sold
                - COALESCE(reserved_quantities.quantity_reserved, 0),
            0) AS quantity_available,
            COALESCE(reserved_quantities.quantity_reserved, 0) AS quantity_reserved,
            CASE WHEN ticket_prices.initial_quantity_available IS NULL
                THEN TRUE
                ELSE FALSE
                END AS unlimited_quantity_available
        FROM tickets
        JOIN ticket_prices ON tickets.id = ticket_prices.ticket_id
        LEFT JOIN reserved_quantities ON tickets.id = reserved_quantities.ticket_id
            AND ticket_prices.id = reserved_quantities.ticket_price_id
        WHERE
            tickets.event_id = :eventId
            AND tickets.deleted_at IS NULL
            AND ticket_prices.deleted_at IS NULL
        GROUP BY tickets.id, ticket_prices.id, reserved_quantities.quantity_reserved;
    SQL, [
            'eventId' => $eventId,
            'reserved' => OrderStatus::RESERVED->name
        ]);

        return collect($result)->map(fn($row) => AvailableTicketQuantitiesDTO::fromArray([
            'ticket_id' => $row->ticket_id,
            'price_id' => $row->ticket_price_id,
            'ticket_title' => $row->ticket_title,
            'price_label' => $row->price_label,
            'quantity_available' => $row->unlimited_quantity_available ? Constants::INFINITE : $row->quantity_available,
            'initial_quantity_available' => $row->initial_quantity_available,
            'quantity_reserved' => $row->quantity_reserved,
            'capacities' => new Collection(),
        ]));
    }

    /**
     * @param Collection<CapacityAssignmentDomainObject> $capacities
     */
    private function calculateTicketCapacities(Collection $capacities): array
    {
        $ticketCapacities = [];
        foreach ($capacities as $capacity) {
            foreach ($capacity->getTickets() as $ticket) {
                $ticketId = $ticket->getId();
                if (!isset($ticketCapacities[$ticketId])) {
                    $ticketCapacities[$ticketId] = collect();
                }

                $ticketCapacities[$ticketId]->push($capacity);
            }
        }

        return $ticketCapacities;
    }

    private function getDataFromCache(int $eventId): ?AvailableTicketQuantitiesResponseDTO
    {
        return $this->cache->get($this->getCacheKey($eventId));
    }

    private function getCacheKey(int $eventId): string
    {
        return "event.$eventId.available_ticket_quantities";
    }
}
