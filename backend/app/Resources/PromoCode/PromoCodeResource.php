<?php

namespace HiEvents\Resources\PromoCode;

use HiEvents\DomainObjects\PromoCodeDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromoCodeDomainObject
 */
class PromoCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'code' => $this->getCode(),
            'applicable_product_ids' => $this->getApplicableProductIds(),
            'discount' => $this->getDiscount(),
            'discount_type' => $this->getDiscountType(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            'expiry_date' => $this->getExpiryDate(),
            'attendee_usage_count' => $this->getAttendeeUsageCount(),
            'order_usage_count' => $this->getOrderUsageCount(),
            'max_allowed_usages' => $this->getMaxAllowedUsages(),
        ];
    }
}
