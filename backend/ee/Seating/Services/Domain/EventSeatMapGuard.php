<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapRelabelRequiresConfirmationException;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatMapDiffDTO;
use Illuminate\Support\Collection;

class EventSeatMapGuard
{
    private const MAX_LISTED_SEATS = 10;

    public function diff(SeatMapIndex $current, SeatMapIndex $incoming): SeatMapDiffDTO
    {
        $currentSeats = $current->seats();
        $incomingSeats = $incoming->seats();
        $kept = array_intersect_key($incomingSeats, $currentSeats);

        return new SeatMapDiffDTO(
            added_seat_count: count(array_diff_key($incomingSeats, $currentSeats)),
            removed_seat_labels: array_values(array_column(array_diff_key($currentSeats, $incomingSeats), 'label')),
            relabelled_seat_count: count(array_filter(
                $kept,
                static fn (array $seat, string $uid) => $seat['label'] !== $currentSeats[$uid]['label'],
                ARRAY_FILTER_USE_BOTH,
            )),
            rebanded_seat_count: count(array_filter(
                $kept,
                static fn (array $seat, string $uid) => $seat['band'] !== $currentSeats[$uid]['band'],
                ARRAY_FILTER_USE_BOTH,
            )),
        );
    }

    /**
     * @param  Collection<EventSeatMapBandProductDomainObject>  $bandProducts
     *
     * @throws SeatMapChangeConflictException
     */
    public function assertLinkedBandsPreserved(Collection $bandProducts, SeatMapIndex $incoming): void
    {
        $missing = $bandProducts
            ->toBase()
            ->map(fn (EventSeatMapBandProductDomainObject $link) => $link->getBandKey())
            ->unique()
            ->diff($incoming->bandKeys());

        if ($missing->isNotEmpty()) {
            throw new SeatMapChangeConflictException(
                __('Unlink the tickets from these bands before removing them: :bands', ['bands' => $missing->implode(', ')])
            );
        }
    }

    /**
     * @param  Collection<int, object{band_key: string, product_id: ?int}>  $liveSeats
     * @param  array<string, int[]>  $productIdsByBand
     *
     * @throws SeatMapChangeConflictException
     */
    public function assertClaimedLinksPreserved(Collection $liveSeats, array $productIdsByBand): void
    {
        $kept = collect($productIdsByBand)
            ->flatMap(fn (array $productIds, string $bandKey) => array_map(fn ($productId) => $bandKey.':'.$productId, $productIds))
            ->all();

        $dropped = $liveSeats
            ->whereNotNull('product_id')
            ->reject(fn (object $seat) => in_array($seat->band_key.':'.$seat->product_id, $kept, true))
            ->pluck('band_key')
            ->unique();

        if ($dropped->isNotEmpty()) {
            throw new SeatMapChangeConflictException(
                __('Seats in these bands are sold or held on an upcoming date, so their tickets cannot be unlinked: :bands', ['bands' => $dropped->implode(', ')])
            );
        }
    }

    /**
     * @param  Collection<int, object{seat_uid: string, seat_label: string}>  $liveSeats
     * @return array<string, string> the new label keyed by seat uid
     */
    public function relabelledSeats(Collection $liveSeats, SeatMapIndex $incoming): array
    {
        return $liveSeats
            ->filter(fn (object $seat) => $incoming->has($seat->seat_uid) && $incoming->labelOf($seat->seat_uid) !== $seat->seat_label)
            ->mapWithKeys(fn (object $seat) => [$seat->seat_uid => $incoming->labelOf($seat->seat_uid)])
            ->all();
    }

    /**
     * @param  Collection<int, object{seat_uid: string, band_key: string, seat_label: string}>  $liveSeats
     * @param  array<string, int>  $zoneOccupancy
     *
     * @throws SeatMapChangeConflictException
     * @throws SeatMapRelabelRequiresConfirmationException
     */
    public function assertCompatibleWithClaims(Collection $liveSeats, array $zoneOccupancy, SeatMapIndex $incoming, bool $allowRelabel = false): void
    {
        $changedSeats = $liveSeats
            ->reject(fn (object $seat) => $incoming->has($seat->seat_uid)
                && $incoming->bandOf($seat->seat_uid) === $seat->band_key)
            ->pluck('seat_label')
            ->unique();

        if ($changedSeats->isNotEmpty()) {
            throw new SeatMapChangeConflictException(__('These seats are sold or held on an upcoming date and cannot be removed or moved to another band: :seats', [
                'seats' => $changedSeats->take(self::MAX_LISTED_SEATS)->implode(', '),
            ]));
        }

        $relabelled = array_keys($this->relabelledSeats($liveSeats, $incoming));

        if (! $allowRelabel && $relabelled !== []) {
            throw new SeatMapRelabelRequiresConfirmationException(
                $liveSeats->whereIn('seat_uid', $relabelled)->pluck('seat_label')->unique()->take(self::MAX_LISTED_SEATS)->values()->all()
            );
        }

        $overfullZones = collect($zoneOccupancy)
            ->filter(fn (int $claimed, string $zoneUid) => $claimed > $incoming->zoneCapacity($zoneUid))
            ->keys()
            ->map(fn (string $zoneUid) => $incoming->labelOf($zoneUid));

        if ($overfullZones->isNotEmpty()) {
            throw new SeatMapChangeConflictException(__('More tickets are already sold for these areas than the new capacity allows: :zones', [
                'zones' => $overfullZones->implode(', '),
            ]));
        }
    }
}
