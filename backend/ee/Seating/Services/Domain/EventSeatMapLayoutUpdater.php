<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\DomainObjects\Generated\EventSeatMapDomainObjectAbstract;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapRelabelRequiresConfirmationException;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;

class EventSeatMapLayoutUpdater
{
    public function __construct(
        private readonly EventSeatMapRepositoryInterface $eventSeatMapRepository,
        private readonly SeatClaimRepositoryInterface $seatClaimRepository,
        private readonly EventSeatMapGuard $guard,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
    ) {}

    /**
     * @param  array<string, mixed>  $extraAttributes
     *
     * @throws SeatMapChangeConflictException
     * @throws SeatMapRelabelRequiresConfirmationException
     */
    public function replaceLayout(
        EventSeatMapDomainObject $eventSeatMap,
        array $layout,
        array $extraAttributes = [],
        bool $allowRelabel = false,
    ): void {
        $incoming = SeatMapIndex::fromLayout($layout);
        $liveSeats = $this->seatClaimRepository->findLiveSeatsForEvent($eventSeatMap->getEventId());

        $this->guard->assertLinkedBandsPreserved($eventSeatMap->getEventSeatMapBandProducts(), $incoming);
        $this->guard->assertCompatibleWithClaims(
            $liveSeats,
            $this->seatClaimRepository->findLiveZoneOccupancyForEvent($eventSeatMap->getEventId()),
            $incoming,
            $allowRelabel,
        );

        $this->eventSeatMapRepository->updateFromArray($eventSeatMap->getId(), [
            EventSeatMapDomainObjectAbstract::LAYOUT => $layout,
            EventSeatMapDomainObjectAbstract::VERSION => $eventSeatMap->getVersion() + 1,
            ...$extraAttributes,
        ]);

        $this->seatClaimRepository->relabel($eventSeatMap->getEventId(), $this->guard->relabelledSeats($liveSeats, $incoming));

        $this->seatClaimRepository->deleteDeadClaimsForRemovedSeats(
            $eventSeatMap->getEventId(),
            [...array_keys($incoming->seats()), ...array_keys($incoming->zones())],
        );

        $this->eventSeatMapLookup->forget($eventSeatMap->getEventId());
    }
}
