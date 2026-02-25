<?php

namespace HiEvents\Services\Application\Handlers\Webhook;

use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\WebhookRepositoryInterface;
use Illuminate\Support\Collection;

class GetWebhooksHandler
{
    public function __construct(
        private readonly WebhookRepositoryInterface $webhookRepository,
    )
    {
    }

    public function handler(int $accountId, ?int $eventId = null, ?int $organizerId = null): Collection
    {
        $where = ['account_id' => $accountId];
        if ($eventId !== null) {
            $where['event_id'] = $eventId;
        }
        if ($organizerId !== null) {
            $where['organizer_id'] = $organizerId;
        }

        return $this->webhookRepository->findWhere(
            where: $where,
            orderAndDirections: [
                new OrderAndDirection('id', OrderAndDirection::DIRECTION_DESC),
            ]
        );
    }
}
