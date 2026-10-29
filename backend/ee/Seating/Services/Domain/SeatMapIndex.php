<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

final class SeatMapIndex
{
    private const SEATED_TYPES = ['row', 'block', 'table'];

    /**
     * @param  array<string, array{label: string, band: string, x: float|int, y: float|int, accessible: bool, companion: bool, area: string}>  $seats
     * @param  array<string, array{label: string, band: string, capacity: int}>  $zones
     * @param  string[]  $bandKeys
     * @param  string[][]  $segments
     * @param  array<string, array{x: float|int, y: float|int}|null>  $focalPoints
     */
    private function __construct(
        private readonly array $seats,
        private readonly array $zones,
        private readonly array $bandKeys,
        private readonly array $segments,
        private readonly array $focalPoints,
    ) {}

    public static function fromLayout(array $layout): self
    {
        $seats = [];
        $zones = [];
        $segments = [];
        $focalPoints = [];

        foreach ($layout['areas'] as $area) {
            $focalPoints[$area['id']] = $area['focal'];

            foreach ($area['elements'] as $element) {
                if ($element['type'] === 'zone') {
                    $zones[$element['id']] = [
                        'label' => "{$area['name']} · {$element['label']}",
                        'band' => $element['band'],
                        'capacity' => $element['capacity'],
                    ];
                }

                if (! in_array($element['type'], self::SEATED_TYPES, true)) {
                    continue;
                }

                $segment = [];
                $previousRow = null;
                foreach ($element['seats'] as $seat) {
                    $seats[$seat['uid']] = [
                        'label' => "{$area['name']} · {$seat['label']}",
                        'band' => $seat['band'],
                        'x' => $seat['x'],
                        'y' => $seat['y'],
                        'accessible' => $seat['acc'],
                        'companion' => ($seat['comp'] ?? false) === true,
                        'area' => $area['id'],
                    ];

                    if ($element['type'] === 'table') {
                        continue;
                    }

                    if ($previousRow !== null && $previousRow !== $seat['row'] && $segment !== []) {
                        $segments[] = $segment;
                        $segment = [];
                    }
                    $segment[] = $seat['uid'];
                    $previousRow = $seat['row'];

                    if ($seat['gapAfter']) {
                        $segments[] = $segment;
                        $segment = [];
                    }
                }
                if ($segment !== []) {
                    $segments[] = $segment;
                }
            }
        }

        return new self($seats, $zones, array_column($layout['bands'], 'key'), $segments, $focalPoints);
    }

    /**
     * @return array<string, array{label: string, band: string, x: float|int, y: float|int, accessible: bool, companion: bool, area: string}>
     */
    public function seats(): array
    {
        return $this->seats;
    }

    /**
     * @return array<string, array{label: string, band: string, capacity: int}>
     */
    public function zones(): array
    {
        return $this->zones;
    }

    /**
     * @return string[]
     */
    public function bandKeys(): array
    {
        return $this->bandKeys;
    }

    /**
     * @return string[][] runs of adjacent row seats, split at aisles and removed seats
     */
    public function segments(): array
    {
        return $this->segments;
    }

    public function distanceToFocalPoint(string $seatUid): float
    {
        $seat = $this->seats[$seatUid];
        $focal = $this->focalPoints[$seat['area']];

        return $focal === null
            ? (float) $seat['y']
            : hypot($seat['x'] - $focal['x'], $seat['y'] - $focal['y']);
    }

    public function has(string $uid): bool
    {
        return isset($this->seats[$uid]) || isset($this->zones[$uid]);
    }

    public function isZone(string $uid): bool
    {
        return isset($this->zones[$uid]);
    }

    public function bandOf(string $uid): string
    {
        return ($this->seats[$uid] ?? $this->zones[$uid])['band'];
    }

    public function labelOf(string $uid): string
    {
        return ($this->seats[$uid] ?? $this->zones[$uid])['label'];
    }

    public function isWheelchairSpace(string $uid): bool
    {
        return $this->seats[$uid]['accessible'] ?? false;
    }

    public function isCompanionSeat(string $uid): bool
    {
        return $this->seats[$uid]['companion'] ?? false;
    }

    public function zoneCapacity(string $uid): int
    {
        return $this->zones[$uid]['capacity'];
    }

    /**
     * @return array<string, int>
     */
    public function capacityByBand(): array
    {
        $capacity = array_fill_keys($this->bandKeys, 0);

        foreach ($this->seats as $seat) {
            $capacity[$seat['band']]++;
        }

        foreach ($this->zones as $zone) {
            $capacity[$zone['band']] += $zone['capacity'];
        }

        return $capacity;
    }
}
