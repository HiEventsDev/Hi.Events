<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;

class EventPageViewIncrementService
{
    private int $batchSize;

    public function __construct(
        private readonly EventStatisticRepositoryInterface $eventStatisticsRepository,
        private readonly CacheManager                      $cacheManager,
        private readonly Repository                        $config,
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

        // If the views count reaches the batch size, update the DB
        if ($viewsCount >= $this->batchSize) {
            $this->batchUpdateDatabase($eventId);

            $this->cacheManager->decrement($eventViewsCacheKey, $this->batchSize);
        }
    }

    private function batchUpdateDatabase(int $eventId): void
    {
        $this->eventStatisticsRepository->incrementWhere(
            where: [
                'event_id' => $eventId,
            ],
            column: 'total_views',
            amount: $this->batchSize,
        );
    }
}
