<?php

namespace HiEvents\Resources\Message;

use HiEvents\DomainObjects\OutgoingMessageDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OutgoingMessageDomainObject
 */
class OutgoingMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'message_id' => $this->getMessageId(),
            'recipient' => $this->getRecipient(),
            'status' => $this->getStatus(),
            'subject' => $this->getSubject(),
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
