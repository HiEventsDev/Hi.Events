<?php

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Services\Domain\Event\EventPageViewIncrementService;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class EventPageViewIncrementServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private EventStatisticRepositoryInterface $eventStatisticsRepository;
    private CacheManager $cacheManager;
    private EventPageViewIncrementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventStatisticsRepository = m::mock(EventStatisticRepositoryInterface::class);
        $this->cacheManager = m::mock(CacheManager::class);
        $config = m::mock(Repository::class);

        $config->shouldReceive('get')
            ->with('app.homepage_views_update_batch_size')
            ->andReturn(100)
            ->once();

        $this->service = new EventPageViewIncrementService(
            $this->eventStatisticsRepository,
            $this->cacheManager,
            $config
        );
    }

    public function testIncrementIgnoresRepeatedViewsFromSameIP(): void
    {
        $eventId = 1;
        $userIp = '127.0.0.1';

        $this->cacheManager->shouldReceive('has')->once()->andReturn(true);

        $this->cacheManager->shouldNotReceive('increment');
        $this->eventStatisticsRepository->shouldNotReceive('incrementWhere');

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
        $this->eventStatisticsRepository->shouldNotReceive('incrementWhere', m::any());

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

        $this->eventStatisticsRepository->shouldReceive('incrementWhere')
            ->once()
            ->with([
                'event_id' => $eventId,
            ], 'total_views', 100);

        $this->service->increment($eventId, $userIp);
    }
}
