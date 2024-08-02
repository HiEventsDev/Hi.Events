<?php

namespace HiEvents\Services\Handlers\Ticket;

use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Services\Domain\Ticket\TicketFilterService;
use Illuminate\Pagination\LengthAwarePaginator;

class GetTicketsHandler
{
    public function __construct(
        private readonly TicketRepositoryInterface $ticketRepository,
        private readonly TicketFilterService       $ticketFilterService,
    )
    {
    }

    public function handle(int $eventId, QueryParamsDTO $queryParamsDTO): LengthAwarePaginator
    {
        $ticketPaginator = $this->ticketRepository
            ->loadRelation(TicketPriceDomainObject::class)
            ->loadRelation(TaxAndFeesDomainObject::class)
            ->findByEventId($eventId, $queryParamsDTO);

        $filteredTickets = $this->ticketFilterService->filter(
            tickets: $ticketPaginator->getCollection(),
            hideSoldOutTickets: false,
        );

        $ticketPaginator->setCollection($filteredTickets);

        return $ticketPaginator;
    }
}
