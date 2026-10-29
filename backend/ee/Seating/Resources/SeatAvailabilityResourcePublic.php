<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Resources;

use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatAvailabilityDTO;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin SeatAvailabilityDTO
 */
class SeatAvailabilityResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->version,
            'unavailable_seat_uids' => $this->unavailable_seat_uids,
            'zone_remaining' => (object) $this->zone_remaining,
            'band_free' => (object) $this->band_free,
        ];
    }
}
