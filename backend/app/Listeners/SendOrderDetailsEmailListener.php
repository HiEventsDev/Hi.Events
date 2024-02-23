<?php

namespace TicketKitten\Listeners;

use TicketKitten\Events\OrderStatusChangedEvent;
use TicketKitten\Jobs\SendOrderDetailsEmailJob;

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
