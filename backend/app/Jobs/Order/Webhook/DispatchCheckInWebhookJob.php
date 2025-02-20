<?php

namespace HiEvents\Jobs\Order\Webhook;

use HiEvents\DomainObjects\Enums\WebhookEventType;
use HiEvents\Services\Infrastructure\Webhook\WebhookDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchCheckInWebhookJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int              $attendeeCheckInId,
        public WebhookEventType $eventType,
    )
    {
    }

    public function handle(WebhookDispatchService $webhookDispatchService): void
    {
        $webhookDispatchService->dispatchCheckInWebhook(
            eventType: $this->eventType,
            attendeeCheckInId: $this->attendeeCheckInId,
        );
    }
}
