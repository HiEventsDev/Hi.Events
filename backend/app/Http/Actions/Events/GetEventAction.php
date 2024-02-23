<?php

declare(strict_types=1);

namespace TicketKitten\Http\Actions\Events;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\TaxAndFeesDomainObject;
use TicketKitten\DomainObjects\TicketDomainObject;
use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Eloquent\Value\Relationship;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Resources\Event\EventResource;

class GetEventAction extends BaseAction
{
    private EventRepositoryInterface $eventRepository;

    public function __construct(EventRepositoryInterface $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $event = $this->eventRepository
            ->loadRelation(
                new Relationship(TicketDomainObject::class, [
                    new Relationship(TicketPriceDomainObject::class),
                    new Relationship(TaxAndFeesDomainObject::class),
                ]),
            )
            ->findById($eventId);

        return $this->resourceResponse(EventResource::class, $event);
    }
}
