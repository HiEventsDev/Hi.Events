<?php

namespace HiEvents\Resources\TransactionMessage;

use HiEvents\DomainObjects\OutgoingTransactionMessageDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OutgoingTransactionMessageDomainObject
 */
class OutgoingTransactionMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'order_id' => $this->getOrderId(),
            'attendee_id' => $this->getAttendeeId(),
            'email_type' => $this->getEmailType(),
            'recipient' => $this->getRecipient(),
            'subject' => $this->getSubject(),
            'status' => $this->getStatus(),
            'resolved_at' => $this->getResolvedAt(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
