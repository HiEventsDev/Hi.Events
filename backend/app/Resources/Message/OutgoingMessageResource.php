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
            /** @var 'SENT'|'FAILED' */
            'status' => $this->getStatus(),
            'delivered_at' => $this->getEmailDelivery()?->getDeliveredAt(),
            'bounced_at' => $this->getEmailDelivery()?->getBouncedAt(),
            'complained_at' => $this->getEmailDelivery()?->getComplainedAt(),
            'subject' => $this->getSubject(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
