<?php

namespace Tests\Unit\Services\Domain\Widget;

use HiEvents\Jobs\Widget\UpdateWidgetViewsJob;
use HiEvents\Services\Domain\Widget\WidgetViewIncrementService;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Queue\QueueManager;
use Mockery as m;
use Tests\TestCase;

class WidgetViewIncrementServiceTest extends TestCase
{
    private CacheManager $cacheManager;

    private QueueManager $queueManager;

    private WidgetViewIncrementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheManager = m::mock(CacheManager::class);
        $this->queueManager = m::mock(QueueManager::class);
        $config = m::mock(Repository::class);
        $config->shouldReceive('get')->with('app.homepage_views_update_batch_size')->andReturn(3);

        $this->service = new WidgetViewIncrementService($this->cacheManager, $config, $this->queueManager);
    }

    public function test_repeat_views_from_the_same_visitor_are_not_counted(): void
    {
        $this->cacheManager->shouldReceive('add')->with('widget_view_viewer_7_1.2.3.4', true, m::any())->once()->andReturn(false);
        $this->cacheManager->shouldNotReceive('increment');
        $this->queueManager->shouldNotReceive('push');

        $this->service->increment(7, '1.2.3.4');
    }

    public function test_views_are_counted_in_cache_until_a_batch_is_full(): void
    {
        $this->cacheManager->shouldReceive('add')->once()->andReturn(true);
        $this->cacheManager->shouldReceive('increment')->with('widget_views_7')->once()->andReturn(2);
        $this->queueManager->shouldNotReceive('push');

        $this->service->increment(7, '1.2.3.4');
    }

    public function test_a_full_batch_is_written_by_a_queued_job(): void
    {
        $this->cacheManager->shouldReceive('add')->once()->andReturn(true);
        $this->cacheManager->shouldReceive('increment')->with('widget_views_7')->once()->andReturn(3);
        $this->cacheManager->shouldReceive('decrement')->with('widget_views_7', 3)->once();
        $this->queueManager->shouldReceive('push')->once()->with(m::type(UpdateWidgetViewsJob::class));

        $this->service->increment(7, '1.2.3.4');
    }

    public function test_pending_views_report_the_unwritten_batch(): void
    {
        $this->cacheManager->shouldReceive('get')->with('widget_views_7', 0)->andReturn(5);
        $this->cacheManager->shouldReceive('get')->with('widget_views_8', 0)->andReturn(null);

        $this->assertSame(5, $this->service->pendingViews(7));
        $this->assertSame(0, $this->service->pendingViews(8));
    }
}
