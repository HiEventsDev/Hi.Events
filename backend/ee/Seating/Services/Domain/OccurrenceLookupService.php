<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;

class OccurrenceLookupService
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function getForEvent(int $eventId, int $occurrenceId): EventOccurrenceDomainObject
    {
        return $this->occurrenceRepository->findFirstWhere([
            EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
        ]) ?? throw new ResourceNotFoundException(__('Event date not found'));
    }
}
