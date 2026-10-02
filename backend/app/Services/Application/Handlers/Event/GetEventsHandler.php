<?php

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\GetEventsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class GetEventsHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventSeatMapRepositoryInterface $eventSeatMapRepository,
    ) {}

    public function handle(GetEventsDTO $dto): LengthAwarePaginator
    {
        $events = $this->eventRepository
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, nested: [
                new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                    new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                ]),
            ]))
            ->loadRelation(new Relationship(ImageDomainObject::class))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(EventStatisticDomainObject::class))
            ->loadRelation(new Relationship(
                domainObject: ProductDomainObject::class,
                nested: [
                    new Relationship(ProductPriceDomainObject::class),
                ],
            ))
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                name: 'organizer',
            ))
            ->findEvents(
                where: [
                    'account_id' => $dto->accountId,
                ],
                params: $dto->queryParams
            );

        $seatedEventIds = $this->eventSeatMapRepository->findEventIdsWithSeatMaps(
            $events->getCollection()->map(fn (EventDomainObject $event) => $event->getId())->all(),
        );

        $events->getCollection()->each(
            fn (EventDomainObject $event) => $event->setHasSeatMap(in_array($event->getId(), $seatedEventIds, true)),
        );

        return $events;
    }
}
