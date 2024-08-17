<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\Constants;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Generated\TicketDomainObjectAbstract;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\CapacityAssignment;
use HiEvents\Models\CheckInList;
use HiEvents\Models\Ticket;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use RuntimeException;

class TicketRepository extends BaseRepository implements TicketRepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [TicketDomainObjectAbstract::EVENT_ID, '=', $eventId]
        ];

        if (!empty($params->query)) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(TicketDomainObjectAbstract::TITLE, 'ilike', '%' . $params->query . '%');
            };
        }

        $this->model = $this->model->orderBy(
            $params->sort_by ?? TicketDomainObject::getDefaultSort(),
            $params->sort_direction ?? TicketDomainObject::getDefaultSortDirection(),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    /**
     * @param int $ticketId
     * @param int $ticketPriceId
     * @return int
     */
    public function getQuantityRemainingForTicketPrice(int $ticketId, int $ticketPriceId): int
    {
        $query = <<<SQL
        SELECT
            COALESCE(ticket_prices.initial_quantity_available, 0) - (
                ticket_prices.quantity_sold + COALESCE((
                    SELECT sum(order_items.quantity)
                    FROM orders
                    INNER JOIN order_items ON orders.id = order_items.order_id
                    WHERE order_items.ticket_price_id = :ticketPriceId
                    AND orders.status in ('RESERVED')
                    AND current_timestamp < orders.reserved_until
                    AND orders.deleted_at IS NULL
                    AND order_items.deleted_at IS NULL
                ), 0)
            ) AS quantity_remaining,
            ticket_prices.initial_quantity_available IS NULL AS unlimited_tickets_available
        FROM ticket_prices
        WHERE ticket_prices.id = :ticketPriceId
        AND ticket_prices.ticket_id = :ticketId
        AND ticket_prices.deleted_at IS NULL
    SQL;

        $result = $this->db->selectOne($query, [
            'ticketPriceId' => $ticketPriceId,
            'ticketId' => $ticketId
        ]);

        if ($result === null) {
            throw new RuntimeException('Ticket price not found');
        }

        if ($result->unlimited_tickets_available) {
            return Constants::INFINITE;
        }

        return (int)$result->quantity_remaining;
    }

    public function getTaxesByTicketId(int $ticketId): Collection
    {
        $query = <<<SQL
            SELECT tf.*
            FROM ticket_taxes_and_fees ttf
            INNER JOIN taxes_and_fees tf ON tf.id = ttf.tax_and_fee_id
            WHERE ttf.ticket_id = :ticketId
            AND tf.deleted_at IS NULL
        SQL;

        $taxAndFees = $this->db->select($query, [
            'ticketId' => $ticketId
        ]);

        return $this->handleResults($taxAndFees, TaxAndFeesDomainObject::class);
    }

    public function getTicketsByTaxId(int $taxId): Collection
    {
        $query = <<<SQL
            SELECT t.*
            FROM ticket_taxes_and_fees ttf
            INNER JOIN tickets t ON t.id = ttf.ticket_id
            WHERE ttf.tax_and_fee_id = :taxAndFeeId
            AND t.deleted_at IS NULL
        SQL;

        $tickets = $this->model->select($query, [
            'taxAndFeeId' => $taxId
        ]);

        return $this->handleResults($tickets, TicketDomainObject::class);
    }

    public function getCapacityAssignmentsByTicketId(int $ticketId): Collection
    {
        $capacityAssignments = CapacityAssignment::whereHas('tickets', static function ($query) use ($ticketId) {
            $query->where('ticket_id', $ticketId);
        })->get();

        return $this->handleResults($capacityAssignments, CapacityAssignmentDomainObject::class);
    }

    public function addTaxesAndFeesToTicket(int $ticketId, array $taxIds): void
    {
        Ticket::findOrFail($ticketId)?->tax_and_fees()->sync($taxIds);
    }

    public function addCapacityAssignmentToTickets(int $capacityAssignmentId, array $ticketIds): void
    {
        $ticketIds = array_unique($ticketIds);

        Ticket::whereNotIn('id', $ticketIds)
            ->whereHas('capacity_assignments', function ($query) use ($capacityAssignmentId) {
                $query->where('capacity_assignment_id', $capacityAssignmentId);
            })
            ->each(function (Ticket $ticket) use ($capacityAssignmentId) {
                $ticket->capacity_assignments()->detach($capacityAssignmentId);
            });

        Ticket::whereIn('id', $ticketIds)
            ->each(function (Ticket $ticket) use ($capacityAssignmentId) {
                $ticket->capacity_assignments()->syncWithoutDetaching([$capacityAssignmentId]);
            });
    }

    public function addCheckInListToTickets(int $checkInListId, array $ticketIds): void
    {
        $ticketIds = array_unique($ticketIds);

        Ticket::whereNotIn('id', $ticketIds)
            ->whereHas('check_in_lists', function ($query) use ($checkInListId) {
                $query->where('check_in_list_id', $checkInListId);
            })
            ->each(function (Ticket $ticket) use ($checkInListId) {
                $ticket->check_in_lists()->detach($checkInListId);
            });

        Ticket::whereIn('id', $ticketIds)
            ->each(function (Ticket $ticket) use ($checkInListId) {
                $ticket->check_in_lists()->syncWithoutDetaching([$checkInListId]);
            });
    }

    public function removeCheckInListFromTickets(int $checkInListId): void
    {
        $checkInList = CheckInList::find($checkInListId);

        $checkInList?->tickets()->detach();
    }

    public function removeCapacityAssignmentFromTickets(int $capacityAssignmentId): void
    {
        $capacityAssignment = CapacityAssignment::find($capacityAssignmentId);

        $capacityAssignment?->tickets()->detach();
    }

    public function sortTickets(int $eventId, array $orderedTicketIds): void
    {
        $parameters = [
            'eventId' => $eventId,
            'ticketIds' => '{' . implode(',', $orderedTicketIds) . '}',
            'orders' => '{' . implode(',', range(1, count($orderedTicketIds))) . '}',
        ];

        $query = "WITH new_order AS (
                  SELECT unnest(:ticketIds::bigint[]) AS ticket_id,
                         unnest(:orders::int[]) AS order
              )
              UPDATE tickets
              SET \"order\" = new_order.order
              FROM new_order
              WHERE tickets.id = new_order.ticket_id AND tickets.event_id = :eventId";

        $this->db->update($query, $parameters);
    }

    public function getModel(): string
    {
        return Ticket::class;
    }

    public function getDomainObject(): string
    {
        return TicketDomainObject::class;
    }
}
