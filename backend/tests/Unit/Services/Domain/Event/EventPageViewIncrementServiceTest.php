<?php

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\Jobs\Event\UpdateEventPageViewsJob;
use HiEvents\Services\Domain\Event\EventPageViewIncrementService;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Queue\QueueManager;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class EventPageViewIncrementServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private CacheManager $cacheManager;
    private QueueManager $queueManager;
    private EventPageViewIncrementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheManager = m::mock(CacheManager::class);
        $this->queueManager = m::mock(QueueManager::class);
        $config = m::mock(Repository::class);

        $config->shouldReceive('get')
            ->with('app.homepage_views_update_batch_size')
            ->andReturn(100)
            ->once();

        $this->service = new EventPageViewIncrementService(
            $this->cacheManager,
            $config,
            $this->queueManager
        );
    }

    public function testIncrementIgnoresRepeatedViewsFromSameIP(): void
    {
        $eventId = 1;
        $userIp = '127.0.0.1';

        $this->cacheManager->shouldReceive('has')->once()->andReturn(true);

        $this->cacheManager->shouldNotReceive('increment');
        $this->queueManager->shouldNotReceive('push');

        $this->service->increment($eventId, $userIp);
    }

    public function testIncrementUpdatesViewCount(): void
    {
        $eventId = 1;
        $userIp = '127.0.0.2';

        $this->cacheManager->shouldReceive('has')->once()->andReturn(false);
        $this->cacheManager->shouldReceive('put')->once();
        $this->cacheManager->shouldReceive('increment')->once()->andReturn(1);

        $this->cacheManager->shouldNotReceive('decrement');
        $this->queueManager->shouldNotReceive('push');

        $this->service->increment($eventId, $userIp);
    }

    public function testBatchUpdateDatabaseIsCalled(): void
    {
        $eventId = 1;
        $userIp = '127.0.0.3';

        $this->cacheManager->shouldReceive('has')->once()->andReturn(false);
        $this->cacheManager->shouldReceive('put')->once();
        $this->cacheManager->shouldReceive('increment')->once()->andReturn(100);
        $this->cacheManager->shouldReceive('decrement')->once()->with('event_views_' . $eventId, 100);

        // The expectation has changed to checking if the job is pushed to the queue
        $this->queueManager->shouldReceive('push')
            ->once()
            ->withArgs(fn($job) => $job instanceof UpdateEventPageViewsJob);

        $this->service->increment($eventId, $userIp);
    }
}
