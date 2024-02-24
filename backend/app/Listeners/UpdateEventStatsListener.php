<?php

namespace HiEvents\Listeners;

use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Jobs\UpdateEventStatisticsJob;

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
