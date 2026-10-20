<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SeatAvailabilityDTO extends BaseDataObject
{
    /**
     * @param  string[]  $unavailable_seat_uids
     * @param  array<string, int>  $zone_remaining
     * @param  array<string, int>  $band_free
     */
    public function __construct(
        public readonly int $version,
        public readonly array $unavailable_seat_uids,
        public readonly array $zone_remaining,
        public readonly array $band_free,
    ) {}
}
