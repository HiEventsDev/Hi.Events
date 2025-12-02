<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Sitemap;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Sitemap\GetSitemapEventsHandler;
use HiEvents\Services\Domain\Sitemap\SitemapGeneratorService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery as m;
use Tests\TestCase;

class GetSitemapEventsHandlerTest extends TestCase
{
    private EventRepositoryInterface $eventRepository;
    private SitemapGeneratorService $sitemapGenerator;
    private GetSitemapEventsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->sitemapGenerator = m::mock(SitemapGeneratorService::class);

        $this->handler = new GetSitemapEventsHandler(
            $this->eventRepository,
            $this->sitemapGenerator,
        );

        config(['sitemap.cache_ttl' => 3600]);
        config(['sitemap.events_per_page' => 1000]);
        config(['app.frontend_url' => 'https://example.com']);
    }

    public function testHandleReturnsCachedXml(): void
    {
        $expectedXml = '<?xml version="1.0"?><urlset></urlset>';

        $this->eventRepository
            ->shouldReceive('getSitemapEventCount')
            ->once()
            ->andReturn(500);

        Cache::shouldReceive('remember')
            ->once()
            ->with('sitemap:events:1', 3600, m::type('Closure'))
            ->andReturn($expectedXml);

        $result = $this->handler->handle(1);

        $this->assertEquals($expectedXml, $result);
    }

    public function testHandleGeneratesXmlWhenCacheMiss(): void
    {
        $expectedXml = '<?xml version="1.0"?><urlset></urlset>';
        $events = new Collection([m::mock(EventDomainObject::class)]);
        $paginator = m::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('getCollection')->andReturn($events);

        $this->eventRepository
            ->shouldReceive('getSitemapEventCount')
            ->once()
            ->andReturn(500);

        $this->eventRepository
            ->shouldReceive('getSitemapEvents')
            ->once()
            ->with(1, 1000)
            ->andReturn($paginator);

        $this->sitemapGenerator
            ->shouldReceive('generateEventsSitemap')
            ->once()
            ->with($events, 'https://example.com')
            ->andReturn($expectedXml);

        Cache::shouldReceive('remember')
            ->once()
            ->with('sitemap:events:1', 3600, m::type('Closure'))
            ->andReturnUsing(fn($key, $ttl, $callback) => $callback());

        $result = $this->handler->handle(1);

        $this->assertEquals($expectedXml, $result);
    }

    public function testHandleThrowsExceptionForPageLessThanOne(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Page must be a positive integer');

        $this->handler->handle(0);
    }

    public function testHandleThrowsExceptionForNegativePage(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Page must be a positive integer');

        $this->handler->handle(-1);
    }

    public function testHandleThrowsExceptionForPageBeyondTotal(): void
    {
        $this->eventRepository
            ->shouldReceive('getSitemapEventCount')
            ->once()
            ->andReturn(500);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Page not found');

        $this->handler->handle(2);
    }

    public function testHandleAllowsLastValidPage(): void
    {
        config(['sitemap.events_per_page' => 100]);

        $events = new Collection([]);
        $paginator = m::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('getCollection')->andReturn($events);

        $this->eventRepository
            ->shouldReceive('getSitemapEventCount')
            ->once()
            ->andReturn(250);

        $this->eventRepository
            ->shouldReceive('getSitemapEvents')
            ->once()
            ->with(3, 100)
            ->andReturn($paginator);

        $this->sitemapGenerator
            ->shouldReceive('generateEventsSitemap')
            ->once()
            ->andReturn('xml');

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn($key, $ttl, $callback) => $callback());

        $result = $this->handler->handle(3);

        $this->assertEquals('xml', $result);
    }

    public function testHandleUsesCorrectCacheKeyForDifferentPages(): void
    {
        $this->eventRepository
            ->shouldReceive('getSitemapEventCount')
            ->andReturn(5000);

        Cache::shouldReceive('remember')
            ->once()
            ->with('sitemap:events:3', 3600, m::type('Closure'))
            ->andReturn('xml');

        $result = $this->handler->handle(3);

        $this->assertEquals('xml', $result);
    }

    public function testHandleTrimsTrailingSlashFromBaseUrl(): void
    {
        config(['app.frontend_url' => 'https://example.com/']);

        $events = new Collection([]);
        $paginator = m::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('getCollection')->andReturn($events);

        $this->eventRepository
            ->shouldReceive('getSitemapEventCount')
            ->once()
            ->andReturn(100);

        $this->eventRepository
            ->shouldReceive('getSitemapEvents')
            ->once()
            ->andReturn($paginator);

        $this->sitemapGenerator
            ->shouldReceive('generateEventsSitemap')
            ->once()
            ->with($events, 'https://example.com')
            ->andReturn('xml');

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn($key, $ttl, $callback) => $callback());

        $result = $this->handler->handle(1);

        $this->assertEquals('xml', $result);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
