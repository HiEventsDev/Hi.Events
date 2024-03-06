<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\BaseRepository;

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
     * @param array $taxIds
     * @return void
     */
    public function addTaxToTicket(int $ticketId, array $taxIds): void;

    /**
     * @param int $eventId
     * @param array $orderedTicketIds
     * @return void
     */
    public function sortTickets(int $eventId, array $orderedTicketIds): void;
}
