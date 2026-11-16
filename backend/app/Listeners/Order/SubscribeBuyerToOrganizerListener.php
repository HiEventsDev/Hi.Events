<?php

namespace HiEvents\Listeners\Order;

use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Jobs\OrganizerSubscription\SubscribeCompletedOrderJob;

class SubscribeBuyerToOrganizerListener
{
    public function handle(OrderStatusChangedEvent $event): void
    {
        if ($event->order->isOrderCompleted() && $event->order->getNewEventSubscriptionRequestedAt() && $event->order->getOptedIntoMarketingAt()) {
            SubscribeCompletedOrderJob::dispatch($event->order->getId())->afterCommit();
        }
    }
}
