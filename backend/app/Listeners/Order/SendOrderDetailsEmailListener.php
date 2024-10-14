<?php

namespace HiEvents\Listeners\Order;

use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Jobs\Order\SendOrderDetailsEmailJob;

class SendOrderDetailsEmailListener
{
    public function handle(OrderStatusChangedEvent $changedEvent): void
    {
        if (!$changedEvent->sendEmails) {
            return;
        }

        dispatch(new SendOrderDetailsEmailJob($changedEvent->order));
    }
}
