<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\DomainObjects\Generated\EventSeatMapDomainObjectAbstract;
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

        return $eventSeatMap === null ? null : $this->availability(
            $eventSeatMap,
            $this->capacitiesFor([$eventSeatMap])[$eventSeatMap->getId()],
            $this->seatClaimRepository->findLiveForOccurrences([$occurrenceId]),
        );
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
        return $this->freeCountByProductForEvents([$eventId => $occurrenceId])[$eventId] ?? [];
    }

    /**
     * @param  array<int, int|null>  $occurrenceIdsByEventId
     * @return array<int, array<int, int>> free places keyed by event id, then seated product id
     */
    public function freeCountByProductForEvents(array $occurrenceIdsByEventId): array
    {
        $eventSeatMaps = array_filter(
            $this->eventSeatMapLookup->findSummariesForEvents(array_keys($occurrenceIdsByEventId)),
            fn (?EventSeatMapDomainObject $eventSeatMap) => $eventSeatMap !== null
                && $eventSeatMap->getEventSeatMapBandProducts()->isNotEmpty(),
        );

        $occurrenceIds = array_values(array_filter(array_intersect_key($occurrenceIdsByEventId, $eventSeatMaps)));
        $claimsByOccurrenceId = $occurrenceIds === []
            ? collect()
            : $this->seatClaimRepository->findLiveForOccurrences($occurrenceIds)->groupBy('event_occurrence_id');
        $capacitiesBySeatMapId = $this->capacitiesFor(array_values($eventSeatMaps));

        $freeCounts = [];
        foreach ($eventSeatMaps as $eventId => $eventSeatMap) {
            $occurrenceId = $occurrenceIdsByEventId[$eventId];
            $capacities = $capacitiesBySeatMapId[$eventSeatMap->getId()];
            $bandFree = $occurrenceId === null
                ? $capacities['bands']
                : $this->availability($eventSeatMap, $capacities, $claimsByOccurrenceId->get($occurrenceId, collect()))->band_free;

            $freeCounts[$eventId] = $eventSeatMap->getEventSeatMapBandProducts()
                ->toBase()
                ->groupBy(fn (EventSeatMapBandProductDomainObject $link) => $link->getProductId())
                ->map(fn ($links) => $links->sum(fn (EventSeatMapBandProductDomainObject $link) => $bandFree[$link->getBandKey()] ?? 0))
                ->all();
        }

        return $freeCounts;
    }

    /**
     * @param  array{bands: array<string, int>, zones: array<string, int>}  $capacities
     */
    private function availability(EventSeatMapDomainObject $eventSeatMap, array $capacities, Collection $claims): SeatAvailabilityDTO
    {
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
     * @param  EventSeatMapDomainObject[]  $eventSeatMaps
     * @return array<int, array{bands: array<string, int>, zones: array<string, int>}> keyed by event seat map id
     */
    private function capacitiesFor(array $eventSeatMaps): array
    {
        $cacheKeys = [];
        foreach ($eventSeatMaps as $eventSeatMap) {
            $cacheKeys[$eventSeatMap->getId()] = sprintf(
                'event_seat_map.%d.v%d.capacities.s%d',
                $eventSeatMap->getId(),
                $eventSeatMap->getVersion(),
                self::CAPACITIES_CACHE_SHAPE,
            );
        }

        $cached = $this->cache->getMultiple(array_values($cacheKeys));

        $capacities = [];
        foreach ($cacheKeys as $eventSeatMapId => $cacheKey) {
            if (is_array($cached[$cacheKey] ?? null)) {
                $capacities[$eventSeatMapId] = $cached[$cacheKey];
            }
        }

        $uncachedIds = array_keys(array_diff_key($cacheKeys, $capacities));
        if ($uncachedIds === []) {
            return $capacities;
        }

        $computed = $this->eventSeatMapRepository
            ->findWhereIn(EventSeatMapDomainObjectAbstract::ID, $uncachedIds)
            ->mapWithKeys(fn (EventSeatMapDomainObject $eventSeatMap) => [
                $eventSeatMap->getId() => $this->capacitiesOfLayout($eventSeatMap->getLayout()),
            ])
            ->all();

        $this->cache->setMultiple(
            array_combine(
                array_map(fn (int $eventSeatMapId) => $cacheKeys[$eventSeatMapId], array_keys($computed)),
                $computed,
            ),
            self::CAPACITIES_CACHE_TTL_SECONDS,
        );

        return $capacities + $computed;
    }

    /**
     * @return array{bands: array<string, int>, zones: array<string, int>}
     */
    private function capacitiesOfLayout(array $layout): array
    {
        $index = SeatMapIndex::fromLayout($layout);

        return [
            'bands' => $index->capacityByBand(),
            'zones' => array_map(static fn (array $zone) => $zone['capacity'], $index->zones()),
        ];
    }
}
