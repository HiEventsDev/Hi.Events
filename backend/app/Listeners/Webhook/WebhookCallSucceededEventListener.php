<?php

namespace HiEvents\Listeners\Webhook;

use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;

class WebhookCallSucceededEventListener extends WebhookCallEventListener
{
    public function handle(WebhookCallSucceededEvent $event): void
    {
        $this->handleEvent($event);
    }
}
