<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\DomainObjects\Generated\EventSeatMapDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\SeatMapDomainObjectAbstract;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;

class AttachEventSeatMapHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly SeatMapRepositoryInterface $seatMapRepository,
        private readonly EventSeatMapRepositoryInterface $eventSeatMapRepository,
        private readonly EventSeatMapLookupService $lookupService,
        private readonly LicensedFeatureUsageService $featureUsage,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $seatMapId, int $accountId): EventSeatMapDomainObject
    {
        $event = $this->eventRepository->findById($eventId);

        $seatMap = $this->seatMapRepository->findFirstWhere([
            SeatMapDomainObjectAbstract::ID => $seatMapId,
            SeatMapDomainObjectAbstract::ORGANIZER_ID => $event->getOrganizerId(),
            SeatMapDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]) ?? throw new ResourceNotFoundException(__('Seat map not found'));

        try {
            $this->eventSeatMapRepository->create([
                EventSeatMapDomainObjectAbstract::EVENT_ID => $eventId,
                EventSeatMapDomainObjectAbstract::SEAT_MAP_ID => $seatMap->getId(),
                EventSeatMapDomainObjectAbstract::SOURCE_VERSION => $seatMap->getVersion(),
                EventSeatMapDomainObjectAbstract::LAYOUT => $seatMap->getLayout(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new ResourceConflictException(__('This event already has a seat map'), previous: $exception);
        }

        $this->lookupService->forget($eventId);
        $this->featureUsage->forget();

        return $this->lookupService->getForEvent($eventId);
    }
}
