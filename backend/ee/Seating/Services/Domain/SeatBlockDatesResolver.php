<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;

class SeatBlockDatesResolver
{
    public const MAX_SEATS_ACROSS_DATES = 50000;

    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
    ) {}

    /**
     * @param  int[]  $occurrenceIds
     * @return int[]
     *
     * @throws ResourceNotFoundException
     * @throws SeatSelectionInvalidException
     */
    public function resolve(int $eventId, bool $allUpcomingDates, array $occurrenceIds, int $seatCount): array
    {
        $resolved = $allUpcomingDates
            ? $this->occurrenceRepository->findUpcomingIdsForEvent($eventId)
            : $this->ownedOccurrenceIds($eventId, $occurrenceIds);

        if (count($resolved) * $seatCount > self::MAX_SEATS_ACROSS_DATES) {
            throw new SeatSelectionInvalidException(__('Too many seats across these dates. Change at most :max seats at once (dates × seats).', [
                'max' => self::MAX_SEATS_ACROSS_DATES,
            ]));
        }

        return $resolved;
    }

    /**
     * @param  int[]  $occurrenceIds
     * @return int[]
     *
     * @throws ResourceNotFoundException
     */
    private function ownedOccurrenceIds(int $eventId, array $occurrenceIds): array
    {
        $owned = $this->occurrenceRepository->findWhere([
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            [EventOccurrenceDomainObjectAbstract::ID, 'in', $occurrenceIds],
        ], [EventOccurrenceDomainObjectAbstract::ID])
            ->map(fn (EventOccurrenceDomainObject $occurrence) => $occurrence->getId())
            ->all();

        if (count($owned) !== count($occurrenceIds)) {
            throw new ResourceNotFoundException(__('Event date not found'));
        }

        return $owned;
    }
}
