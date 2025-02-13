<?php

namespace HiEvents\Jobs\Order\Webhook;

use HiEvents\DomainObjects\Enums\WebhookEventType;
use HiEvents\Services\Infrastructure\Webhook\WebhookDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchAttendeeWebhookJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int              $attendeeId,
        public WebhookEventType $eventType,
    )
    {
    }

    public function handle(WebhookDispatchService $webhookDispatchService): void
    {
        $webhookDispatchService->dispatchAttendeeWebhook(
            eventType: $this->eventType,
            attendeeId: $this->attendeeId,
        );
    }
}
