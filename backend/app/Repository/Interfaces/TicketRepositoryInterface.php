<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<TicketDomainObject>
 */
interface TicketRepositoryInterface extends RepositoryInterface
{
    /**
     * @param int $eventId
     * @param QueryParamsDTO $params
     * @return LengthAwarePaginator
     */
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    /**
     * @param int $ticketId
     * @param int $ticketPriceId
     * @return int
     */
    public function getQuantityRemainingForTicketPrice(int $ticketId, int $ticketPriceId): int;

    /**
     * @param int $ticketId
     * @return Collection
     */
    public function getTaxesByTicketId(int $ticketId): Collection;

    /**
     * @param int $taxId
     * @return Collection
     */
    public function getTicketsByTaxId(int $taxId): Collection;

    /**
     * @param int $ticketId
     * @return Collection
     */
    public function getCapacityAssignmentsByTicketId(int $ticketId): Collection;

    /**
     * @param int $ticketId
     * @param array $taxIds
     * @return void
     */
    public function addTaxesAndFeesToTicket(int $ticketId, array $taxIds): void;

    /**
     * @param array $ticketIds
     * @param int $capacityAssignmentId
     * @return void
     */
    public function addCapacityAssignmentToTickets(int $capacityAssignmentId, array $ticketIds): void;

    /**
     * @param int $checkInListId
     * @param array $ticketIds
     * @return void
     */
    public function addCheckInListToTickets(int $checkInListId, array $ticketIds): void;

    /**
     * @param int $checkInListId
     * @return void
     */
    public function removeCheckInListFromTickets(int $checkInListId): void;

    /**
     * @param int $capacityAssignmentId
     * @return void
     */
    public function removeCapacityAssignmentFromTickets(int $capacityAssignmentId): void;

    /**
     * @param int $eventId
     * @param array $orderedTicketIds
     * @return void
     */
    public function sortTickets(int $eventId, array $orderedTicketIds): void;
}
