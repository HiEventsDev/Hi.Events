<?php

namespace HiEvents\Services\Application\Handlers\Webhook;

use HiEvents\Repository\Interfaces\WebhookLogRepositoryInterface;
use HiEvents\Repository\Interfaces\WebhookRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class DeleteWebhookHandler
{
    public function __construct(
        private readonly WebhookRepositoryInterface    $webhookRepository,
        private readonly WebhookLogRepositoryInterface $webhookLogRepository,
        private readonly DatabaseManager               $databaseManager,
    )
    {
    }

    public function handle(int $eventId, int $webhookId): void
    {
        $this->databaseManager->transaction(function () use ($eventId, $webhookId) {
            $webhook = $this->webhookRepository->findFirstWhere([
                'id' => $webhookId,
                'event_id' => $eventId,
            ]);

            if (!$webhook) {
                throw new ResourceNotFoundException(__(
                    key: 'Webhook not found for ID: :webhookId and event ID: :eventId',
                    replace: [
                        'webhookId' => $webhookId,
                        'eventId' => $eventId,
                    ]
                ));
            }

            $this->webhookRepository->deleteWhere([
                'id' => $webhookId,
                'event_id' => $eventId,
            ]);

            $this->webhookLogRepository
                ->deleteOldLogs($webhookId, numberToKeep: 0);
        });
    }
}
