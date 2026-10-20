<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\DomainObjects\Generated\EventSeatMapDomainObjectAbstract;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\EventSeatMapRulesDTO;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;

class UpdateEventSeatMapRulesHandler
{
    public function __construct(
        private readonly EventSeatMapRepositoryInterface $eventSeatMapRepository,
        private readonly EventSeatMapLookupService $lookupService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, EventSeatMapRulesDTO $rules): EventSeatMapDomainObject
    {
        $this->eventSeatMapRepository->updateFromArray($this->lookupService->getForEvent($eventId)->getId(), [
            EventSeatMapDomainObjectAbstract::PREVENT_ORPHAN_SEATS => $rules->prevent_orphan_seats,
            EventSeatMapDomainObjectAbstract::MAX_SEATS_PER_ORDER => $rules->max_seats_per_order,
            EventSeatMapDomainObjectAbstract::ALLOW_SEAT_CHANGE => $rules->allow_seat_change,
        ]);
        $this->lookupService->forget($eventId);

        return $this->lookupService->getForEvent($eventId);
    }
}
