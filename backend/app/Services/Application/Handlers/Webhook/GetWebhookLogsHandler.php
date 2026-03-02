<?php

namespace HiEvents\Services\Application\Handlers\Webhook;

use HiEvents\Repository\Interfaces\WebhookLogRepositoryInterface;
use HiEvents\Repository\Interfaces\WebhookRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetWebhookLogsHandler
{
    public function __construct(
        private readonly WebhookLogRepositoryInterface $webhookLogRepository,
        private readonly WebhookRepositoryInterface    $webhookRepository,
    )
    {
    }

    public function handle(int $webhookId, ?int $eventId = null, ?int $organizerId = null): LengthAwarePaginator
    {
        $where = ['id' => $webhookId];
        if ($eventId !== null) {
            $where['event_id'] = $eventId;
        }
        if ($organizerId !== null) {
            $where['organizer_id'] = $organizerId;
        }

        $webhook = $this->webhookRepository->findFirstWhere(
            where: $where
        );

        if (!$webhook) {
            throw new ResourceNotFoundException(__('Webhook not found'));
        }

        return $this->webhookLogRepository
            ->paginateWhere(
                where: [
                    'webhook_id' => $webhook->getId(),
                ],
                limit: 10,
            );
    }
}
