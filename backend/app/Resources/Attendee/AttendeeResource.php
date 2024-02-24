<?php

namespace HiEvents\Resources\Attendee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Resources\Order\OrderResource;

/**
 * @mixin AttendeeDomainObject
 */
class AttendeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'order_id' => $this->getOrderId(),
            'ticket_id' => $this->getTicketId(),
            'ticket_price_id' => $this->getTicketPriceId(),
            'event_id' => $this->getEventId(),
            'email' => $this->getEmail(),
            'status' => $this->getStatus(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'public_id' => $this->getPublicId(),
            'checked_in_at' => $this->getCheckedInAt(),
            'checked_in_by' => $this->getCheckedInBy(),
            'short_id' => $this->getShortId(),
            'order' => $this->when(
                !is_null($this->getOrder()),
                fn() => new OrderResource($this->getOrder())
            ),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }

}
