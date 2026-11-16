<?php

declare(strict_types=1);

namespace HiEvents\Resources\Badge;

use HiEvents\Repository\DTO\BadgeStatsDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BadgeStatsDTO
 */
class BadgeStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->total,
            'printed' => $this->printed,
            'not_printed' => $this->not_printed,
            'needs_reprint' => $this->needs_reprint,
        ];
    }
}
