<?php

namespace HiEvents\Resources\EventOccurrence;

use HiEvents\DomainObjects\ProductOccurrenceVisibilityDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin ProductOccurrenceVisibilityDomainObject
 */
class ProductOccurrenceVisibilityResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'event_occurrence_id' => $this->getEventOccurrenceId(),
            'product_id' => $this->getProductId(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
