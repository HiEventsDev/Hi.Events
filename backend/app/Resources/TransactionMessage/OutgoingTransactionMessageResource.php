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
            'resolution_type' => $this->getResolutionType(),
            'retry_for_id' => $this->getRetryForId(),
            'retry_count' => $this->getRetryCount(),
            'latest_retry_recipient' => $this->getLatestRetryRecipient(),
            'latest_retry_status' => $this->getLatestRetryStatus(),
            'original_recipient' => $this->getOriginalRecipient(),
            'original_status' => $this->getOriginalStatus(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
