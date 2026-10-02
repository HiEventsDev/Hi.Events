<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\DomainObjects\Generated\EventSeatMapDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\SeatMapDomainObjectAbstract;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use HiEvents\Exceptions\ResourceNotFoundException;

class EventSeatMapLookupService
{
    private const SOURCE_COLUMNS = [
        SeatMapDomainObjectAbstract::ID,
        SeatMapDomainObjectAbstract::NAME,
        SeatMapDomainObjectAbstract::VERSION,
    ];

    private const SUMMARY_COLUMNS = [
        EventSeatMapDomainObjectAbstract::ID,
        EventSeatMapDomainObjectAbstract::EVENT_ID,
        EventSeatMapDomainObjectAbstract::VERSION,
    ];

    /**
     * @var array<int, EventSeatMapDomainObject|null>
     */
    private array $eventSeatMaps = [];

    /**
     * @var array<int, EventSeatMapDomainObject|null>
     */
    private array $summaries = [];

    /**
     * @var array<int, SeatMapIndex>
     */
    private array $indexes = [];

    public function __construct(
        private readonly EventSeatMapRepositoryInterface $eventSeatMapRepository,
        private readonly SeatMapRepositoryInterface $seatMapRepository,
    ) {}

    public function existsForEvent(int $eventId): bool
    {
        return $this->findSummaryForEvent($eventId) !== null;
    }

    public function findSummaryForEvent(int $eventId): ?EventSeatMapDomainObject
    {
        if (array_key_exists($eventId, $this->eventSeatMaps)) {
            return $this->eventSeatMaps[$eventId];
        }

        if (array_key_exists($eventId, $this->summaries)) {
            return $this->summaries[$eventId];
        }

        return $this->summaries[$eventId] = $this->eventSeatMapRepository
            ->loadRelation(EventSeatMapBandProductDomainObject::class)
            ->findFirstWhere([EventSeatMapDomainObjectAbstract::EVENT_ID => $eventId], self::SUMMARY_COLUMNS);
    }

    public function findForEvent(int $eventId): ?EventSeatMapDomainObject
    {
        if (array_key_exists($eventId, $this->eventSeatMaps)) {
            return $this->eventSeatMaps[$eventId];
        }

        if (array_key_exists($eventId, $this->summaries) && $this->summaries[$eventId] === null) {
            return null;
        }

        return $this->eventSeatMaps[$eventId] = $this->eventSeatMapRepository
            ->loadRelation(EventSeatMapBandProductDomainObject::class)
            ->findFirstWhere([EventSeatMapDomainObjectAbstract::EVENT_ID => $eventId]);
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function getForEvent(int $eventId): EventSeatMapDomainObject
    {
        $eventSeatMap = $this->findForEvent($eventId)
            ?? throw new ResourceNotFoundException(__('This event has no seat map'));

        if ($eventSeatMap->getSeatMapId() !== null) {
            $eventSeatMap->setSeatMap($this->seatMapRepository->findFirstWhere(
                [SeatMapDomainObjectAbstract::ID => $eventSeatMap->getSeatMapId()],
                self::SOURCE_COLUMNS,
            ));
        }

        return $eventSeatMap;
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function indexFor(int $eventId): SeatMapIndex
    {
        return $this->indexes[$eventId] ??= SeatMapIndex::fromLayout(
            ($this->findForEvent($eventId) ?? throw new ResourceNotFoundException(__('This event has no seat map')))->getLayout()
        );
    }

    public function bandOf(int $eventId, string $seatUid): ?string
    {
        if ($this->findForEvent($eventId) === null) {
            return null;
        }

        $index = $this->indexFor($eventId);

        return $index->has($seatUid) ? $index->bandOf($seatUid) : null;
    }

    public function forget(int $eventId): void
    {
        unset($this->eventSeatMaps[$eventId], $this->summaries[$eventId], $this->indexes[$eventId]);
    }
}
