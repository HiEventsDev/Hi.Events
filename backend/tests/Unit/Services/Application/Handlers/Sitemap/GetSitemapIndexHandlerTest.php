<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Sitemap;

use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Sitemap\GetSitemapIndexHandler;
use HiEvents\Services\Domain\Sitemap\SitemapGeneratorService;
use Illuminate\Support\Facades\Cache;
use Mockery as m;
use Tests\TestCase;

class GetSitemapIndexHandlerTest extends TestCase
{
    private EventRepositoryInterface $eventRepository;
    private OrganizerRepositoryInterface $organizerRepository;
    private SitemapGeneratorService $sitemapGenerator;
    private GetSitemapIndexHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->organizerRepository = m::mock(OrganizerRepositoryInterface::class);
        $this->sitemapGenerator = m::mock(SitemapGeneratorService::class);

        $this->handler = new GetSitemapIndexHandler(
            $this->eventRepository,
            $this->organizerRepository,
            $this->sitemapGenerator,
        );

        config(['sitemap.cache_ttl' => 3600]);
        config(['sitemap.events_per_page' => 1000]);
        config(['sitemap.organizers_per_page' => 1000]);
        config(['app.frontend_url' => 'https://example.com']);
    }

    public function testHandleReturnsCachedXml(): void
    {
        $expectedXml = '<?xml version="1.0"?><sitemapindex></sitemapindex>';

        Cache::shouldReceive('remember')
            ->once()
            ->with('sitemap:index', 3600, m::type('Closure'))
            ->andReturn($expectedXml);

        $result = $this->handler->handle();

        $this->assertEquals($expectedXml, $result);
    }

    public function testHandleGeneratesXmlWhenCacheMiss(): void
    {
        $expectedXml = '<?xml version="1.0"?><sitemapindex></sitemapindex>';

        $this->eventRepository
            ->shouldReceive('getSitemapEventCount')
            ->once()
            ->andReturn(2500);

        $this->organizerRepository
            ->shouldReceive('getSitemapOrganizerCount')
            ->once()
            ->andReturn(500);

        $this->sitemapGenerator
            ->shouldReceive('generateSitemapIndex')
            ->once()
            ->with(3, 1, 'https://example.com', m::type('string'))
            ->andReturn($expectedXml);

        Cache::shouldReceive('remember')
            ->once()
            ->with('sitemap:index', 3600, m::type('Closure'))
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->handler->handle();

        $this->assertEquals($expectedXml, $result);
    }

    public function testHandleCalculatesCorrectPageCount(): void
    {
        config(['sitemap.events_per_page' => 500]);
        config(['sitemap.organizers_per_page' => 500]);

        $this->eventRepository
            ->shouldReceive('getSitemapEventCount')
            ->once()
            ->andReturn(1250);

        $this->organizerRepository
            ->shouldReceive('getSitemapOrganizerCount')
            ->once()
            ->andReturn(750);

        $this->sitemapGenerator
            ->shouldReceive('generateSitemapIndex')
            ->once()
            ->with(3, 2, m::any(), m::any())
            ->andReturn('xml');

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn($key, $ttl, $callback) => $callback());

        $result = $this->handler->handle();

        $this->assertEquals('xml', $result);
    }

    public function testHandleReturnsAtLeastOnePage(): void
    {
        $this->eventRepository
            ->shouldReceive('getSitemapEventCount')
            ->once()
            ->andReturn(0);

        $this->organizerRepository
            ->shouldReceive('getSitemapOrganizerCount')
            ->once()
            ->andReturn(0);

        $this->sitemapGenerator
            ->shouldReceive('generateSitemapIndex')
            ->once()
            ->with(1, 1, m::any(), m::any())
            ->andReturn('xml');

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn($key, $ttl, $callback) => $callback());

        $result = $this->handler->handle();

        $this->assertEquals('xml', $result);
    }

    public function testHandleTrimsTrailingSlashFromBaseUrl(): void
    {
        config(['app.frontend_url' => 'https://example.com/']);

        $this->eventRepository
            ->shouldReceive('getSitemapEventCount')
            ->once()
            ->andReturn(100);

        $this->organizerRepository
            ->shouldReceive('getSitemapOrganizerCount')
            ->once()
            ->andReturn(50);

        $this->sitemapGenerator
            ->shouldReceive('generateSitemapIndex')
            ->once()
            ->with(1, 1, 'https://example.com', m::any())
            ->andReturn('xml');

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn($key, $ttl, $callback) => $callback());

        $result = $this->handler->handle();

        $this->assertEquals('xml', $result);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
