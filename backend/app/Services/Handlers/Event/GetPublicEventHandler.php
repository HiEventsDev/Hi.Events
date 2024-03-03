<?php

namespace HiEvents\Services\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Domain\Ticket\TicketFilterService;

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
