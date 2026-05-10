<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventOccurrenceStatisticDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Exceptions\ResourceNotFoundException;

class GetEventOccurrenceHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
    )
    {
    }

    public function handle(int $eventId, int $occurrenceId): EventOccurrenceDomainObject
    {
        $occurrence = $this->occurrenceRepository
            ->loadRelation(EventOccurrenceStatisticDomainObject::class)
            ->findFirstWhere([
            EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$occurrence) {
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
