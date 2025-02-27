<?php

namespace HiEvents\Services\Application\Handlers\Webhook;

use HiEvents\DomainObjects\WebhookDomainObject;
use HiEvents\Repository\Interfaces\WebhookRepositoryInterface;

class GetWebhookHandler
{
    public function __construct(
        private readonly WebhookRepositoryInterface $webhookRepository,
    )
    {
    }

    public function handle(int $eventId, int $webhookId): WebhookDomainObject
    {
        return $this->webhookRepository->findFirstWhere(
            where: [
                'id' => $webhookId,
                'event_id' => $eventId,
            ]
        );
    }
}
