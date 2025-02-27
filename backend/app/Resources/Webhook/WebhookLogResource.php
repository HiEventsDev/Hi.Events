<?php

namespace HiEvents\Resources\Webhook;

use HiEvents\DomainObjects\WebhookLogDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin WebhookLogDomainObject
 */
class WebhookLogResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'webhook_id' => $this->getWebhookId(),
            'payload' => $this->getPayload(),
            'response_body' => $this->getResponseBody(),
            'response_code' => $this->getResponseCode(),
            'created_at' => $this->getCreatedAt(),
            'event_type' => $this->getEventType(),
        ];
    }
}
