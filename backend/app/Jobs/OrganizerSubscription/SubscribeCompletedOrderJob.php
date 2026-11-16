<?php

namespace HiEvents\Jobs\OrganizerSubscription;

use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\OrganizerSubscription\SubscribeToOrganizerHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SubscribeCompletedOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly int $orderId) {}

    public function handle(OrderRepositoryInterface $orders, EventRepositoryInterface $events, SubscribeToOrganizerHandler $handler): void
    {
        $order = $orders->findFirstWhere(['id' => $this->orderId]);
        if (! $order || ! $order->isOrderCompleted() || ! $order->getNewEventSubscriptionRequestedAt() || ! $order->getOptedIntoMarketingAt()) {
            return;
        }
        $event = $events->findFirstWhere(['id' => $order->getEventId(), 'status' => 'LIVE']);
        if ($event !== null) {
            $handler->handle($event->getOrganizerId(), $order->getEmail(), $order->getLocale(), 'checkout', $order->getId());
        }
    }
}
