<?php

namespace HiEvents\Resources\EventOccurrence;

use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\OccurrenceProductAvailabilityDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OccurrenceProductAvailabilityDTO
 */
class OccurrenceProductAvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'product_price_id' => $this->product_price_id,
            'quantity_sold' => $this->quantity_sold,
            'quantity_available' => $this->quantity_available,
        ];
    }
}
