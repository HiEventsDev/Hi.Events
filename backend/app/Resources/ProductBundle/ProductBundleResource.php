<?php

namespace HiEvents\Resources\ProductBundle;

use HiEvents\DomainObjects\ProductBundleDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductBundleDomainObject
 */
class ProductBundleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'price' => $this->getPrice(),
            'currency' => $this->getCurrency(),
            'max_per_order' => $this->getMaxPerOrder(),
            'quantity_available' => $this->getQuantityAvailable(),
            'quantity_sold' => $this->getQuantitySold(),
            'sale_start_date' => $this->getSaleStartDate(),
            'sale_end_date' => $this->getSaleEndDate(),
            'is_active' => $this->getIsActive(),
            'sort_order' => $this->getSortOrder(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
