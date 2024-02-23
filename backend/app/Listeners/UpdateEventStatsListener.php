<?php

namespace TicketKitten\Listeners;

use TicketKitten\Events\OrderStatusChangedEvent;
use TicketKitten\Jobs\UpdateEventStatisticsJob;

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
