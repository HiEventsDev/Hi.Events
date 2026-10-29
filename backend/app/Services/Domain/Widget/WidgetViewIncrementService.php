<?php

namespace HiEvents\Services\Domain\Widget;

use HiEvents\Jobs\Widget\UpdateWidgetViewsJob;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Queue\QueueManager;

class WidgetViewIncrementService
{
    private int $batchSize;

    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly Repository $config,
        private readonly QueueManager $queueManager,
    ) {
        $this->batchSize = $this->config->get('app.homepage_views_update_batch_size');
    }

    public function increment(int $widgetId, string $ipAddress): void
    {
        if (! $this->cacheManager->add('widget_view_viewer_'.$widgetId.'_'.$ipAddress, true, now()->addMinutes(5))) {
            return;
        }

        $pendingViews = $this->cacheManager->increment($this->pendingViewsCacheKey($widgetId));

        if ($pendingViews >= $this->batchSize) {
            $this->queueManager->push(new UpdateWidgetViewsJob($widgetId, $this->batchSize));
            $this->cacheManager->decrement($this->pendingViewsCacheKey($widgetId), $this->batchSize);
        }
    }

    public function pendingViews(int $widgetId): int
    {
        return max(0, (int) $this->cacheManager->get($this->pendingViewsCacheKey($widgetId), 0));
    }

    private function pendingViewsCacheKey(int $widgetId): string
    {
        return 'widget_views_'.$widgetId;
    }
}
