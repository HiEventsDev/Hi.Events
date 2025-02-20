<?php

namespace HiEvents\Listeners\Webhook;

use Spatie\WebhookServer\Events\WebhookCallFailedEvent;

class WebhookCallFailedEventListener extends WebhookCallEventListener
{
    public function handle(WebhookCallFailedEvent $event): void
    {
        $this->handleEvent($event);
    }
}
