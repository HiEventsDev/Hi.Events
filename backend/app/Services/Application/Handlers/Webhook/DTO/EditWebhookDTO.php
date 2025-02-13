<?php

namespace HiEvents\Services\Application\Handlers\Webhook\DTO;

use HiEvents\DomainObjects\Status\WebhookStatus;

class EditWebhookDTO extends CreateWebhookDTO
{
    public function __construct(
        public int    $webhookId,
        string        $url,
        array         $eventTypes,
        int           $eventId,
        int           $userId,
        int           $accountId,
        WebhookStatus $status,
    )
    {
        parent::__construct(
            url: $url,
            eventTypes: $eventTypes,
            eventId: $eventId,
            userId: $userId,
            accountId: $accountId,
            status: $status,
        );
    }
}
