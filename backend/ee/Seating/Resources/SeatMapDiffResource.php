<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Resources;

use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatMapDiffDTO;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin SeatMapDiffDTO
 */
class SeatMapDiffResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'added_seat_count' => $this->added_seat_count,
            'removed_seat_labels' => $this->removed_seat_labels,
            'relabelled_seat_count' => $this->relabelled_seat_count,
            'rebanded_seat_count' => $this->rebanded_seat_count,
        ];
    }
}
