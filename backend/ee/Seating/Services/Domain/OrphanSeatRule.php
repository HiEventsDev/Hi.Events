<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

class OrphanSeatRule
{
    /**
     * @param  string[][]  $segments
     * @param  string[]  $takenUids
     * @param  string[]  $selectedUids
     * @return string[]
     */
    public function findOrphans(array $segments, array $takenUids, array $selectedUids): array
    {
        $taken = array_flip($takenUids);
        $selected = array_flip($selectedUids);
        $orphans = [];

        foreach ($segments as $segment) {
            foreach ($this->freeRuns($segment, $taken) as $run) {
                $orphans = [...$orphans, ...$this->orphansInRun($run, $selected)];
            }
        }

        return $orphans;
    }

    /**
     * @param  string[]  $segment
     * @param  array<string, int>  $excluded
     * @return string[][]
     */
    private function freeRuns(array $segment, array $excluded): array
    {
        $runs = [];
        $current = [];

        foreach ($segment as $uid) {
            if (isset($excluded[$uid])) {
                $runs[] = $current;
                $current = [];

                continue;
            }
            $current[] = $uid;
        }
        $runs[] = $current;

        return array_values(array_filter($runs));
    }

    /**
     * @param  string[]  $run
     * @param  array<string, int>  $selected
     * @return string[]
     */
    private function orphansInRun(array $run, array $selected): array
    {
        $selectedInRun = count(array_filter($run, static fn (string $uid) => isset($selected[$uid])));

        if ($selectedInRun === 0 || count($run) - $selectedInRun === 1) {
            return [];
        }

        $strandedSeats = array_filter(
            $this->freeRuns($run, $selected),
            static fn (array $remaining) => count($remaining) === 1,
        );

        return array_merge([], ...array_values($strandedSeats));
    }
}
