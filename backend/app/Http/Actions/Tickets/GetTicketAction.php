<?php

declare(strict_types=1);

namespace TicketKitten\Http\Actions\Tickets;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\Generated\TicketDomainObjectAbstract;
use TicketKitten\DomainObjects\TaxAndFeesDomainObject;
use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\TicketRepositoryInterface;
use TicketKitten\Resources\Ticket\TicketResource;

class GetTicketAction extends BaseAction
{
    private TicketRepositoryInterface $ticketRepository;

    public function __construct(TicketRepositoryInterface $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    public function __invoke(int $eventId, int $ticketId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(TicketResource::class, $this->ticketRepository
            ->loadRelation(TaxAndFeesDomainObject::class)
            ->loadRelation(TicketPriceDomainObject::class)
            ->findFirstWhere([
                TicketDomainObjectAbstract::EVENT_ID => $eventId,
                TicketDomainObjectAbstract::ID => $ticketId,
            ]));
    }
}
