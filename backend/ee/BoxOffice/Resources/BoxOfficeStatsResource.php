<?php

namespace HiEvents\Enterprise\BoxOffice\Resources;

use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO\BoxOfficeStatsDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BoxOfficeStatsDTO
 */
class BoxOfficeStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'currency' => $this->currency,
            'orders' => $this->orders,
            'gross' => $this->gross,
            'refunded' => $this->refunded,
            'by_tender' => $this->by_tender,
            'by_operator' => $this->by_operator,
            'by_day' => $this->by_day,
        ];
    }
}
