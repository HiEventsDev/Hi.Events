<?php

namespace HiEvents\Resources\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin AttendeeDomainObject
 */
class AdminAttendeeResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $order = $this->getOrder();
        $event = $order?->getEvent();
        $account = $event?->getAccount();
        $product = $this->getProduct();

        return [
            'id' => $this->getId(),
            'order_id' => $this->getOrderId(),
            'product_id' => $this->getProductId(),
            'event_id' => $this->getEventId(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'email' => $this->getEmail(),
            'status' => $this->getStatus(),
            'public_id' => $this->getPublicId(),
            'short_id' => $this->getShortId(),
            'notes' => $this->getNotes(),
            'checked_in_at' => $this->getCheckedInAt(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            'product_title' => $product?->getTitle(),
            'order_short_id' => $order?->getShortId(),
            'event_title' => $event?->getTitle(),
            'account_name' => $account?->getName(),
            'account_id' => $account?->getId(),
        ];
    }
}
