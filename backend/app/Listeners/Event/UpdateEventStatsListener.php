<?php

namespace HiEvents\Listeners\Event;

use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Jobs\Event\UpdateEventStatisticsJob;

readonly class UpdateEventStatsListener
{
    public function handle(OrderStatusChangedEvent $changedEvent): void
    {
        if (!$changedEvent->order->isOrderCompleted()) {
            return;
        }

        dispatch(new UpdateEventStatisticsJob($changedEvent->order));
    }
}
