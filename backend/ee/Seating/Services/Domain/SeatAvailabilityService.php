<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\DomainObjects\Status\SeatClaimStatus;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\OccupiedSeatDTO;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatAvailabilityDTO;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;

class SeatAvailabilityService
{
    private const CAPACITIES_CACHE_TTL_SECONDS = 86400;

    private const CAPACITIES_CACHE_SHAPE = 2;

    public function __construct(
        private readonly EventSeatMapRepositoryInterface $eventSeatMapRepository,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
        private readonly SeatClaimRepositoryInterface $seatClaimRepository,
        private readonly Cache $cache,
    ) {}

    public function forOccurrence(int $eventId, int $occurrenceId): ?SeatAvailabilityDTO
    {
        $eventSeatMap = $this->eventSeatMapLookup->findSummaryForEvent($eventId);

        return $eventSeatMap === null ? null : $this->availability($eventSeatMap, $occurrenceId);
    }

    /**
     * @return Collection<int, OccupiedSeatDTO>
     */
    public function occupiedSeats(int $occurrenceId): Collection
    {
        return $this->seatClaimRepository->findLiveWithHoldersForOccurrence($occurrenceId)
            ->map(fn (object $claim) => new OccupiedSeatDTO(
                seat_uid: $claim->seat_uid,
                seat_label: $claim->seat_label,
                is_zone: (bool) $claim->is_zone,
                band_key: $claim->band_key,
                status: SeatClaimStatus::fromName($claim->status),
                block_reason: $claim->block_reason,
                attendee_public_id: $claim->attendee_public_id,
                attendee_name: $claim->attendee_public_id === null ? null : trim($claim->attendee_first_name.' '.$claim->attendee_last_name),
            ));
    }

    /**
     * @return array<int, int> free places keyed by seated product id
     */
    public function freeCountByProduct(int $eventId, ?int $occurrenceId): array
    {
        $eventSeatMap = $this->eventSeatMapLookup->findSummaryForEvent($eventId);
        if ($eventSeatMap === null || $eventSeatMap->getEventSeatMapBandProducts()->isEmpty()) {
            return [];
        }

        $bandFree = $occurrenceId === null
            ? $this->capacities($eventSeatMap)['bands']
            : $this->availability($eventSeatMap, $occurrenceId)->band_free;

        return $eventSeatMap->getEventSeatMapBandProducts()
            ->toBase()
            ->groupBy(fn (EventSeatMapBandProductDomainObject $link) => $link->getProductId())
            ->map(fn ($links) => $links->sum(fn (EventSeatMapBandProductDomainObject $link) => $bandFree[$link->getBandKey()] ?? 0))
            ->all();
    }

    private function availability(EventSeatMapDomainObject $eventSeatMap, int $occurrenceId): SeatAvailabilityDTO
    {
        $capacities = $this->capacities($eventSeatMap);
        $claims = $this->seatClaimRepository->findLiveForOccurrence($occurrenceId);

        $bandFree = $capacities['bands'];
        foreach ($claims as $claim) {
            if (isset($bandFree[$claim->band_key])) {
                $bandFree[$claim->band_key] = max(0, $bandFree[$claim->band_key] - 1);
            }
        }

        $zoneClaimed = $claims->where('is_zone', true)->countBy('seat_uid');
        $zoneRemaining = [];
        foreach ($capacities['zones'] as $zoneUid => $capacity) {
            $zoneRemaining[$zoneUid] = max(0, $capacity - ($zoneClaimed[$zoneUid] ?? 0));
        }

        return new SeatAvailabilityDTO(
            version: $eventSeatMap->getVersion(),
            unavailable_seat_uids: $claims->where('is_zone', false)->pluck('seat_uid')->values()->all(),
            zone_remaining: $zoneRemaining,
            band_free: $bandFree,
        );
    }

    /**
     * @return array{bands: array<string, int>, zones: array<string, int>}
     */
    private function capacities(EventSeatMapDomainObject $eventSeatMap): array
    {
        return $this->cache->remember(
            sprintf('event_seat_map.%d.v%d.capacities.s%d', $eventSeatMap->getId(), $eventSeatMap->getVersion(), self::CAPACITIES_CACHE_SHAPE),
            self::CAPACITIES_CACHE_TTL_SECONDS,
            function () use ($eventSeatMap) {
                $index = SeatMapIndex::fromLayout(
                    $this->eventSeatMapRepository->findById($eventSeatMap->getId())->getLayout()
                );

                return [
                    'bands' => $index->capacityByBand(),
                    'zones' => array_map(static fn (array $zone) => $zone['capacity'], $index->zones()),
                ];
            },
        );
    }
}
