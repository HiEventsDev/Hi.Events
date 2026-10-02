<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\BoxOffice\Resources;

use HiEvents\Enterprise\Seating\Services\Domain\DTO\OccupiedSeatDTO;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin OccupiedSeatDTO
 */
class BoxOfficeOccupiedSeatResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'seat_uid' => $this->seat_uid,
            'seat_label' => $this->seat_label,
            'is_zone' => $this->is_zone,
            'band_key' => $this->band_key,
            /** @var 'HELD'|'SOLD'|'BLOCKED' */
            'status' => $this->status->name,
            'block_reason' => $this->block_reason,
        ];
    }
}
