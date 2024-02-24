<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Tickets;

use Illuminate\Http\JsonResponse;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\TicketDomainObjectAbstract;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Resources\Ticket\TicketResource;

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
