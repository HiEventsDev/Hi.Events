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

    public function handle(int $webhookId, int $accountId, ?int $eventId = null, ?int $organizerId = null): WebhookDomainObject
    {
        $where = ['id' => $webhookId, 'account_id' => $accountId];
        if ($eventId !== null) {
            $where['event_id'] = $eventId;
        }
        if ($organizerId !== null) {
            $where['organizer_id'] = $organizerId;
        }

        return $this->webhookRepository->findFirstWhere(
            where: $where
        );
    }
}
