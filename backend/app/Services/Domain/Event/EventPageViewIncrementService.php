<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\Jobs\Event\UpdateEventPageViewsJob;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Queue\QueueManager;

class EventPageViewIncrementService
{
    private int $batchSize;

    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly Repository   $config,
        private readonly QueueManager $queueManager,
    )
    {
        $this->batchSize = $this->config->get('app.homepage_views_update_batch_size');
    }

    public function increment(int $eventId, string $userIpAddress): void
    {
        $eventViewsCacheKey = 'event_views_' . $eventId;
        $userViewCacheKey = 'event_view_user_' . $eventId . '_' . $userIpAddress;

        if ($this->cacheManager->has($userViewCacheKey)) {
            return;
        }

        $this->cacheManager->put($userViewCacheKey, true, now()->addMinutes(5));

        $viewsCount = $this->cacheManager->increment($eventViewsCacheKey);

        // If the views count reaches the batch size, queue the job to update the DB
        if ($viewsCount >= $this->batchSize) {
            $this->queueManager->push(new UpdateEventPageViewsJob($eventId, $this->batchSize));

            $this->cacheManager->decrement($eventViewsCacheKey, $this->batchSize);
        }
    }
}
