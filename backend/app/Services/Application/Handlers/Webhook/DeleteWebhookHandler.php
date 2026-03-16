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

    public function handle(int $webhookId, int $accountId, ?int $eventId = null, ?int $organizerId = null): void
    {
        $this->databaseManager->transaction(function () use ($eventId, $webhookId, $organizerId, $accountId) {
            $where = ['id' => $webhookId, 'account_id' => $accountId];
            if ($eventId !== null) {
                $where['event_id'] = $eventId;
            }
            if ($organizerId !== null) {
                $where['organizer_id'] = $organizerId;
            }

            $webhook = $this->webhookRepository->findFirstWhere($where);

            if (!$webhook) {
                throw new ResourceNotFoundException(__(
                    key: 'Webhook not found for ID: :webhookId',
                    replace: [
                        'webhookId' => $webhookId,
                    ]
                ));
            }

            $this->webhookRepository->deleteWhere($where);

            $this->webhookLogRepository
                ->deleteOldLogs($webhookId, numberToKeep: 0);
        });
    }
}
