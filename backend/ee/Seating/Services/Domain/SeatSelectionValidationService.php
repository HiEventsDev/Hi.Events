<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;

class SeatSelectionValidationService
{
    public function __construct(
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
        private readonly SeatedProductLookupService $seatedProductLookup,
        private readonly CompanionSeatRule $companionSeatRule,
    ) {}

    /**
     * @param  array<int, array{product_id: int, event_occurrence_id: int, quantities: array}>  $products
     *
     * @throws SeatSelectionInvalidException
     */
    public function validate(int $eventId, array $products, bool $enforceSelectionRules = true): void
    {
        $eventSeatMap = $this->eventSeatMapLookup->findForEvent($eventId);
        $bandsByProduct = $eventSeatMap === null ? [] : $this->seatedProductLookup->bandKeysByProduct($eventId);
        $index = $eventSeatMap === null ? null : $this->eventSeatMapLookup->indexFor($eventId);

        if ($enforceSelectionRules) {
            $this->assertWithinMaxSeatsPerOrder($eventSeatMap, $products);
        }

        $claimedSeats = [];
        $seatUidsByOccurrence = [];
        foreach ($products as $product) {
            $allowedBands = $bandsByProduct[$product['product_id']] ?? null;

            foreach ($product['quantities'] as $line) {
                $seatUids = $line['seat_uids'] ?? [];

                if ($allowedBands === null) {
                    if ($seatUids !== []) {
                        throw new SeatSelectionInvalidException(__('Seats cannot be selected for this ticket'));
                    }

                    continue;
                }

                if (count($seatUids) !== (int) $line['quantity']) {
                    throw new SeatSelectionInvalidException(__('Please choose a seat for every ticket'));
                }

                foreach ($seatUids as $seatUid) {
                    if (! $index->has($seatUid) || ! in_array($index->bandOf($seatUid), $allowedBands, true)) {
                        throw new SeatSelectionInvalidException(__('One of the selected seats is not available for this ticket'));
                    }

                    $seatKey = $product['event_occurrence_id'].':'.$seatUid;
                    if (! $index->isZone($seatUid) && isset($claimedSeats[$seatKey])) {
                        throw new SeatSelectionInvalidException(__('The same seat was selected more than once'));
                    }
                    $claimedSeats[$seatKey] = true;
                    $seatUidsByOccurrence[$product['event_occurrence_id']][] = $seatUid;
                }

                $this->assertSeatsShareTheLineBand($index, $seatUids, $line['band_key'] ?? null);
            }
        }

        if ($enforceSelectionRules) {
            foreach ($seatUidsByOccurrence as $seatUids) {
                $this->companionSeatRule->assertAccompanied($index, $seatUids);
            }
        }
    }

    /**
     * @param  string[]  $seatUids
     *
     * @throws SeatSelectionInvalidException
     */
    private function assertSeatsShareTheLineBand(SeatMapIndex $index, array $seatUids, ?string $bandKey): void
    {
        if ($seatUids === []) {
            return;
        }

        $bands = array_unique(array_map(fn (string $seatUid) => $index->bandOf($seatUid), $seatUids));

        if (count($bands) > 1) {
            throw new SeatSelectionInvalidException(__('Seats from different price bands cannot share one ticket line'));
        }

        if ($bandKey !== null && $bandKey !== $bands[array_key_first($bands)]) {
            throw new SeatSelectionInvalidException(__('The selected seats do not belong to that price band'));
        }
    }

    /**
     * @throws SeatSelectionInvalidException
     */
    private function assertWithinMaxSeatsPerOrder(?EventSeatMapDomainObject $eventSeatMap, array $products): void
    {
        $maxSeats = $eventSeatMap?->getMaxSeatsPerOrder();
        $requested = collect($products)->sum(
            fn (array $product) => collect($product['quantities'])->sum(fn (array $line) => count($line['seat_uids'] ?? []))
        );

        if ($maxSeats !== null && $requested > $maxSeats) {
            throw new SeatSelectionInvalidException(__('You can choose at most :max seats per order', ['max' => $maxSeats]));
        }
    }
}
