<?php

namespace HiEvents\Resources\Waitlist;

use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Product\ProductPriceResource;
use HiEvents\Resources\Product\ProductResource;
use Illuminate\Http\Request;

/**
 * @mixin WaitlistEntryDomainObject
 */
class WaitlistEntryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'product_price_id' => $this->getProductPriceId(),
            'email' => $this->getEmail(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'status' => $this->getStatus(),
            'position' => $this->getPosition(),
            'offered_at' => $this->getOfferedAt(),
            'offer_expires_at' => $this->getOfferExpiresAt(),
            'purchased_at' => $this->getPurchasedAt(),
            'cancelled_at' => $this->getCancelledAt(),
            'order_id' => $this->getOrderId(),
            'locale' => $this->getLocale(),
            'product' => $this->getProductPrice()?->getProduct()
                ? new ProductResource($this->getProductPrice()?->getProduct())
                : null,
            'product_price' => $this->getProductPrice()
                ? new ProductPriceResource($this->getProductPrice())
                : null,
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
