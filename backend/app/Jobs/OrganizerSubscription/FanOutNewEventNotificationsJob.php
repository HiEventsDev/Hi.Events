<?php

namespace HiEvents\Jobs\OrganizerSubscription;

use HiEvents\Repository\Eloquent\OrganizerEventSubscriptionRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class FanOutNewEventNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $eventId, public readonly int $organizerId, public readonly string $publishedAt, public readonly int $generation, public readonly int $afterId = 0) {}

    public function handle(OrganizerEventSubscriptionRepository $subscriptions): void
    {
        $ids = $subscriptions->createDeliveryBatch($this->eventId, $this->organizerId, $this->publishedAt, $this->generation, $this->afterId);
        foreach ($ids as $id) {
            SendNewEventNotificationJob::dispatch($this->eventId, $id)->afterCommit();
        }
        if (count($ids) === 200) {
            self::dispatch($this->eventId, $this->organizerId, $this->publishedAt, $this->generation, end($ids))->afterCommit();
        }
    }
}
