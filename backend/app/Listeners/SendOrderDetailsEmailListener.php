<?php

namespace HiEvents\Listeners;

use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Jobs\SendOrderDetailsEmailJob;

readonly class SendOrderDetailsEmailListener
{
    public function handle(OrderStatusChangedEvent $changedEvent): void
    {
        if (!$changedEvent->sendEmails) {
            return;
        }

        dispatch(new SendOrderDetailsEmailJob($changedEvent->order));
    }
}
