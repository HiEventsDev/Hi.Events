<?php

namespace HiEvents\Resources\Message;

use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MessageDomainObject
 */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'subject' => $this->getSubject(),
            'message' => $this->getMessage(),
            'type' => $this->getType(),
            'attendee_ids' => $this->getAttendeeIds(),
            'order_id' => $this->getOrderId(),
            'product_ids' => $this->getProductIds(),
            'sent_at' => $this->getCreatedAt(),
            'status' => $this->getStatus(),
            'message_preview' => $this->getMessagePreview(),
            $this->mergeWhen(!is_null($this->getSentByUser()), fn() => [
                'sent_by_user' => new UserResource($this->getSentByUser()),
            ]),
        ];
    }
}
