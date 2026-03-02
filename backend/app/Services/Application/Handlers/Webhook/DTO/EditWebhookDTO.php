<?php

namespace HiEvents\Services\Application\Handlers\Webhook\DTO;

use HiEvents\DomainObjects\Status\WebhookStatus;

class EditWebhookDTO extends CreateWebhookDTO
{
    public function __construct(
        public int    $webhookId,
        string        $url,
        array         $eventTypes,
        int           $userId,
        int           $accountId,
        WebhookStatus $status,
        ?int          $eventId = null,
        ?int          $organizerId = null,
    )
    {
        parent::__construct(
            url: $url,
            eventTypes: $eventTypes,
            userId: $userId,
            accountId: $accountId,
            status: $status,
            eventId: $eventId,
            organizerId: $organizerId,
        );
    }
}
