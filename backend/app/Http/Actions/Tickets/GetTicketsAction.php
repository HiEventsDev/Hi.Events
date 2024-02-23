<?php

declare(strict_types=1);

namespace TicketKitten\Http\Actions\Tickets;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\TaxAndFeesDomainObject;
use TicketKitten\DomainObjects\TicketDomainObject;
use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\QueryParamsDTO;
use TicketKitten\Repository\Interfaces\TicketRepositoryInterface;
use TicketKitten\Resources\Ticket\TicketResource;

class GetTicketsAction extends BaseAction
{
    private TicketRepositoryInterface $ticketRepository;

    public function __construct(TicketRepositoryInterface $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $tickets = $this->ticketRepository
            ->loadRelation(TicketPriceDomainObject::class)
            ->loadRelation(TaxAndFeesDomainObject::class)
            ->findByEventId($eventId, QueryParamsDTO::fromArray($request->query->all()));

        return $this->filterableResourceResponse(
            resource: TicketResource::class,
            data: $tickets,
            domainObject: TicketDomainObject::class
        );
    }
}
