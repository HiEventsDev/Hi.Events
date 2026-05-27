<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventOccurrenceStatisticDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;

class GetEventOccurrenceHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
    ) {}

    public function handle(int $eventId, int $occurrenceId): EventOccurrenceDomainObject
    {
        $occurrence = $this->occurrenceRepository
            ->loadRelation(EventOccurrenceStatisticDomainObject::class)
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->findFirstWhere([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ]);

        if (! $occurrence) {
            throw new ResourceNotFoundException(
                __('Occurrence :id not found for event :eventId', [
                    'id' => $occurrenceId,
                    'eventId' => $eventId,
                ])
            );
        }

        return $occurrence;
    }
}
