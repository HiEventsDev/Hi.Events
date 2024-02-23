<?php

namespace TicketKitten\Service\Handler\Event;

use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\EventSettingDomainObject;
use TicketKitten\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use TicketKitten\DomainObjects\ImageDomainObject;
use TicketKitten\DomainObjects\TaxAndFeesDomainObject;
use TicketKitten\DomainObjects\TicketDomainObject;
use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Repository\Eloquent\Value\Relationship;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\PromoCodeRepositoryInterface;
use TicketKitten\Service\Common\Ticket\TicketFilterService;

readonly class GetPublicEventHandler
{
    public function __construct(
        private EventRepositoryInterface     $eventRepository,
        private PromoCodeRepositoryInterface $promoCodeRepository,
        private TicketFilterService          $ticketFilterService,
    )
    {
    }

    public function handle(int $eventId, string $promoCode = null): EventDomainObject
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
            ->findById($eventId);

        $promoCodeDomainObject = $this->promoCodeRepository->findFirstWhere([
            PromoCodeDomainObjectAbstract::EVENT_ID => $eventId,
            PromoCodeDomainObjectAbstract::CODE => $promoCode,
        ]);

        if (!$promoCodeDomainObject?->isValid()) {
            $promoCodeDomainObject = null;
        }

        return $event->setTickets($this->ticketFilterService->filter($event, $promoCodeDomainObject));
    }
}
