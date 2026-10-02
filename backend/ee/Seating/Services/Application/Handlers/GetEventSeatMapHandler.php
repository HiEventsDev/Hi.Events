<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;

class GetEventSeatMapHandler
{
    public function __construct(
        private readonly EventSeatMapLookupService $lookupService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId): EventSeatMapDomainObject
    {
        return $this->lookupService->getForEvent($eventId);
    }
}
