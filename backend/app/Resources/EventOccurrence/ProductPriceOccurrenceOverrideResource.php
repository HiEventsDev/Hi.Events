<?php

namespace HiEvents\Resources\EventOccurrence;

use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin ProductPriceOccurrenceOverrideDomainObject
 */
class ProductPriceOccurrenceOverrideResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'event_occurrence_id' => $this->getEventOccurrenceId(),
            'product_price_id' => $this->getProductPriceId(),
            'price' => $this->getPrice(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
