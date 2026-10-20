<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

class BestAvailableSeatService
{
    public function __construct(
        private readonly OrphanSeatRule $orphanSeatRule,
        private readonly CompanionSeatRule $companionSeatRule,
    ) {}

    /**
     * @param  string[]  $bandKeys
     * @param  string[]  $unavailableSeatUids
     * @return string[] the chosen seat uids, or an empty array when fewer than $quantity seats are free
     */
    public function find(
        SeatMapIndex $index,
        array $bandKeys,
        array $unavailableSeatUids,
        int $quantity,
        bool $accessibleOnly = false,
        bool $preventOrphans = false,
    ): array {
        $unavailable = array_flip($unavailableSeatUids);
        $isCandidate = static fn (string $uid, array $seat) => ! isset($unavailable[$uid])
            && in_array($seat['band'], $bandKeys, true)
            && ($accessibleOnly
                ? $seat['accessible'] || $seat['companion']
                : ! $seat['accessible'] && ! $seat['companion']);

        $candidates = array_filter($index->seats(), fn (array $seat, string $uid) => $isCandidate($uid, $seat), ARRAY_FILTER_USE_BOTH);

        if (count($candidates) < $quantity) {
            return [];
        }

        $selections = [
            fn () => $this->bestWindow($index, $candidates, $unavailableSeatUids, $quantity, avoidOrphans: true),
            fn () => $this->bestWindow($index, $candidates, $unavailableSeatUids, $quantity, avoidOrphans: false),
            fn () => $this->nearestWithinOneArea($index, $candidates, $quantity),
            fn () => $this->nearest($index, array_keys($candidates), $quantity),
        ];

        foreach ($selections as $selection) {
            $seatUids = $selection();

            if ($seatUids === null) {
                continue;
            }

            if (! $preventOrphans || $this->orphanSeatRule->findOrphans($index->segments(), $unavailableSeatUids, $seatUids) === []) {
                return $seatUids;
            }
        }

        return [];
    }

    /**
     * @param  array<string, array>  $candidates
     * @param  string[]  $unavailableSeatUids
     * @return string[]|null
     */
    private function bestWindow(SeatMapIndex $index, array $candidates, array $unavailableSeatUids, int $quantity, bool $avoidOrphans): ?array
    {
        $best = null;
        $bestDistance = INF;

        foreach ($index->segments() as $segment) {
            for ($start = 0; $start + $quantity <= count($segment); $start++) {
                $window = array_slice($segment, $start, $quantity);

                if (array_diff_key(array_flip($window), $candidates) !== []) {
                    continue;
                }

                if ($this->companionSeatRule->excessCompanions(array_map(fn (string $uid) => $candidates[$uid], $window)) > 0) {
                    continue;
                }

                if ($avoidOrphans && $this->orphanSeatRule->findOrphans([$segment], $unavailableSeatUids, $window) !== []) {
                    continue;
                }

                $distance = $this->meanDistance($index, $window);
                if ($distance < $bestDistance) {
                    $best = $window;
                    $bestDistance = $distance;
                }
            }
        }

        return $best;
    }

    /**
     * @param  array<string, array>  $candidates
     * @return string[]|null
     */
    private function nearestWithinOneArea(SeatMapIndex $index, array $candidates, int $quantity): ?array
    {
        $byArea = [];
        foreach ($candidates as $uid => $seat) {
            $byArea[$seat['area']][] = $uid;
        }

        $best = null;
        $bestDistance = INF;
        foreach ($byArea as $uids) {
            if (count($uids) < $quantity) {
                continue;
            }

            $nearest = $this->nearest($index, $uids, $quantity);
            if ($nearest === null) {
                continue;
            }

            $distance = $this->meanDistance($index, $nearest);
            if ($distance < $bestDistance) {
                $best = $nearest;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @param  string[]  $uids
     * @return string[]|null
     */
    private function nearest(SeatMapIndex $index, array $uids, int $quantity): ?array
    {
        usort($uids, static fn (string $a, string $b) => $index->distanceToFocalPoint($a) <=> $index->distanceToFocalPoint($b));

        $picked = [];
        $wheelchairSpaces = 0;
        $companions = 0;
        foreach ($uids as $uid) {
            if (count($picked) === $quantity) {
                break;
            }

            if ($index->isCompanionSeat($uid)) {
                if ($companions >= $wheelchairSpaces) {
                    continue;
                }
                $companions++;
            } elseif ($index->isWheelchairSpace($uid)) {
                $wheelchairSpaces++;
            }

            $picked[] = $uid;
        }

        return count($picked) === $quantity ? $picked : null;
    }

    /**
     * @param  string[]  $uids
     */
    private function meanDistance(SeatMapIndex $index, array $uids): float
    {
        return array_sum(array_map(static fn (string $uid) => $index->distanceToFocalPoint($uid), $uids)) / count($uids);
    }
}
