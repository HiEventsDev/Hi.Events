<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Tickets;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Resources\Ticket\TicketResource;

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
