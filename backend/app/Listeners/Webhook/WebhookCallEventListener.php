<?php

namespace HiEvents\Listeners\Webhook;

use HiEvents\Services\Infrastructure\Webhook\WebhookResponseHandlerService;
use RuntimeException;
use Spatie\WebhookServer\Events\WebhookCallEvent;

abstract class WebhookCallEventListener
{
    public function __construct(
        private readonly WebhookResponseHandlerService $webhookResponseHandlerService,
    )
    {
    }

    protected function handleEvent(WebhookCallEvent $event): void
    {
        $eventId = $event->meta['event_id'] ?? throw new RuntimeException('Event ID not found in webhook meta');
        $webhookId = $event->meta['webhook_id'] ?? throw new RuntimeException('Webhook ID not found in webhook meta');
        $eventType = $event->meta['event_type'] ?? throw new RuntimeException('Event type not found in webhook meta');

        $this->webhookResponseHandlerService->handleResponse(
            $eventId,
            $webhookId,
            $eventType,
            $event->payload,
            $event->response,
        );
    }
}
