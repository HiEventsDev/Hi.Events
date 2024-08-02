<?php

namespace HiEvents\Services\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Domain\Event\EventPageViewIncrementService;
use HiEvents\Services\Domain\Ticket\TicketFilterService;
use HiEvents\Services\Handlers\Event\DTO\GetPublicEventDTO;

class GetPublicEventHandler
{
    public function __construct(
        private readonly EventRepositoryInterface      $eventRepository,
        private readonly PromoCodeRepositoryInterface  $promoCodeRepository,
        private readonly TicketFilterService           $ticketFilterService,
        private readonly EventPageViewIncrementService $eventPageViewIncrementService,
    )
    {
    }

    public function handle(GetPublicEventDTO $data): EventDomainObject
    {
        $event = $this->eventRepository
            ->loadRelation(
                new Relationship(TicketDomainObject::class, [
                    new Relationship(TicketPriceDomainObject::class),
                    new Relationship(TaxAndFeesDomainObject::class)
                ])
            )
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(ImageDomainObject::class))
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->findById($data->eventId);

        $promoCodeDomainObject = $this->promoCodeRepository->findFirstWhere([
            PromoCodeDomainObjectAbstract::EVENT_ID => $data->eventId,
            PromoCodeDomainObjectAbstract::CODE => $data->promoCode,
        ]);

        if (!$promoCodeDomainObject?->isValid()) {
            $promoCodeDomainObject = null;
        }

        if (!$data->isAuthenticated) {
            $this->eventPageViewIncrementService->increment($data->eventId, $data->ipAddress);
        }

        return $event->setTickets($this->ticketFilterService->filter($event->getTickets(), $promoCodeDomainObject));
    }
}
