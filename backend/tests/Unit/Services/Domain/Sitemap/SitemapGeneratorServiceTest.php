<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Sitemap;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Services\Domain\Sitemap\SitemapGeneratorService;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class SitemapGeneratorServiceTest extends TestCase
{
    private SitemapGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SitemapGeneratorService();
    }

    public function testGenerateSitemapIndexWithSinglePage(): void
    {
        $baseUrl = 'https://example.com';
        $lastMod = '2025-01-15T10:30:00+00:00';

        $xml = $this->service->generateSitemapIndex(1, 1, $baseUrl, $lastMod);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringContainsString('<loc>https://example.com/sitemap-events-1.xml</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/sitemap-organizers-1.xml</loc>', $xml);
        $this->assertStringContainsString('<lastmod>2025-01-15T10:30:00+00:00</lastmod>', $xml);
        $this->assertStringContainsString('</sitemapindex>', $xml);
    }

    public function testGenerateSitemapIndexWithMultiplePages(): void
    {
        $baseUrl = 'https://example.com';
        $lastMod = '2025-01-15T10:30:00+00:00';

        $xml = $this->service->generateSitemapIndex(3, 2, $baseUrl, $lastMod);

        $this->assertStringContainsString('<loc>https://example.com/sitemap-events-1.xml</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/sitemap-events-2.xml</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/sitemap-events-3.xml</loc>', $xml);
        $this->assertStringNotContainsString('sitemap-events-4.xml', $xml);
        $this->assertStringContainsString('<loc>https://example.com/sitemap-organizers-1.xml</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/sitemap-organizers-2.xml</loc>', $xml);
        $this->assertStringNotContainsString('sitemap-organizers-3.xml', $xml);
    }

    public function testGenerateSitemapIndexIsValidXml(): void
    {
        $xml = $this->service->generateSitemapIndex(2, 1, 'https://example.com', '2025-01-15T10:30:00+00:00');

        $dom = new \DOMDocument();
        $result = $dom->loadXML($xml);

        $this->assertTrue($result, 'Generated XML should be valid');
    }

    public function testGenerateEventsSitemapWithUpcomingEvent(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $event = $this->createMockEvent(
            id: 123,
            title: 'My Amazing Event',
            startDate: '2025-02-01 18:00:00',
            updatedAt: '2025-01-10 12:00:00'
        );

        $events = new Collection([$event]);
        $baseUrl = 'https://example.com';

        $xml = $this->service->generateEventsSitemap($events, $baseUrl);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringContainsString('<loc>https://example.com/event/123/my-amazing-event</loc>', $xml);
        $this->assertStringContainsString('<changefreq>daily</changefreq>', $xml);
        $this->assertStringContainsString('<priority>0.8</priority>', $xml);

        Carbon::setTestNow();
    }

    public function testGenerateEventsSitemapWithPastEvent(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $event = $this->createMockEvent(
            id: 456,
            title: 'Past Concert',
            startDate: '2024-12-01 18:00:00',
            updatedAt: '2024-11-15 12:00:00'
        );

        $events = new Collection([$event]);
        $baseUrl = 'https://example.com';

        $xml = $this->service->generateEventsSitemap($events, $baseUrl);

        $this->assertStringContainsString('<loc>https://example.com/event/456/past-concert</loc>', $xml);
        $this->assertStringContainsString('<changefreq>weekly</changefreq>', $xml);
        $this->assertStringContainsString('<priority>0.5</priority>', $xml);

        Carbon::setTestNow();
    }

    public function testGenerateEventsSitemapWithSpecialCharactersInTitle(): void
    {
        $event = $this->createMockEvent(
            id: 789,
            title: 'Event with Special <Characters> & "Quotes"',
            startDate: '2025-02-01 18:00:00',
            updatedAt: '2025-01-10 12:00:00'
        );

        $events = new Collection([$event]);
        $xml = $this->service->generateEventsSitemap($events, 'https://example.com');

        $dom = new \DOMDocument();
        $result = $dom->loadXML($xml);

        $this->assertTrue($result, 'XML with special characters should be valid');
        $this->assertStringContainsString('event-with-special-characters-quotes', $xml);
    }

    public function testGenerateEventsSitemapWithEmptySlugFallsBackToDefault(): void
    {
        $event = $this->createMockEvent(
            id: 101,
            title: '日本語タイトル',
            startDate: '2025-02-01 18:00:00',
            updatedAt: '2025-01-10 12:00:00'
        );

        $events = new Collection([$event]);
        $xml = $this->service->generateEventsSitemap($events, 'https://example.com');

        $this->assertStringContainsString('/event/101/', $xml);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
    }

    public function testGenerateEventsSitemapWithNullStartDate(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $event = $this->createMockEvent(
            id: 202,
            title: 'TBD Event',
            startDate: null,
            updatedAt: '2025-01-10 12:00:00'
        );

        $events = new Collection([$event]);
        $xml = $this->service->generateEventsSitemap($events, 'https://example.com');

        $this->assertStringContainsString('<changefreq>weekly</changefreq>', $xml);
        $this->assertStringContainsString('<priority>0.5</priority>', $xml);

        Carbon::setTestNow();
    }

    public function testGenerateEventsSitemapIncludesLastModFromUpdatedAt(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 10:00:00', 'UTC'));

        $event = $this->createMockEvent(
            id: 303,
            title: 'New Event',
            startDate: '2025-02-01 18:00:00',
            updatedAt: '2025-01-12 15:30:00'
        );

        $events = new Collection([$event]);
        $xml = $this->service->generateEventsSitemap($events, 'https://example.com');

        $this->assertStringContainsString('<lastmod>', $xml);
        $this->assertStringContainsString('2025-01-12', $xml);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));

        Carbon::setTestNow();
    }

    public function testGenerateEventsSitemapWithEmptyCollection(): void
    {
        $events = new Collection([]);
        $xml = $this->service->generateEventsSitemap($events, 'https://example.com');

        $this->assertStringContainsString('urlset', $xml);
        $this->assertStringContainsString('http://www.sitemaps.org/schemas/sitemap/0.9', $xml);
        $this->assertStringNotContainsString('<url>', $xml);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
    }

    public function testGenerateEventsSitemapWithMultipleEvents(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $events = new Collection([
            $this->createMockEvent(1, 'Event One', '2025-02-01 18:00:00', '2025-01-10 12:00:00'),
            $this->createMockEvent(2, 'Event Two', '2025-03-01 18:00:00', '2025-01-11 12:00:00'),
            $this->createMockEvent(3, 'Event Three', '2024-12-01 18:00:00', '2024-11-15 12:00:00'),
        ]);

        $xml = $this->service->generateEventsSitemap($events, 'https://example.com');

        $this->assertStringContainsString('<loc>https://example.com/event/1/event-one</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/event/2/event-two</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/event/3/event-three</loc>', $xml);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $urls = $dom->getElementsByTagName('url');
        $this->assertEquals(3, $urls->length);

        Carbon::setTestNow();
    }

    public function testGenerateOrganizersSitemapWithOrganizer(): void
    {
        $organizer = $this->createMockOrganizer(
            id: 123,
            name: 'My Amazing Organizer',
            updatedAt: '2025-01-10 12:00:00'
        );

        $organizers = new Collection([$organizer]);
        $baseUrl = 'https://example.com';

        $xml = $this->service->generateOrganizersSitemap($organizers, $baseUrl);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringContainsString('<loc>https://example.com/events/123/my-amazing-organizer</loc>', $xml);
        $this->assertStringContainsString('<changefreq>weekly</changefreq>', $xml);
        $this->assertStringContainsString('<priority>0.6</priority>', $xml);
    }

    public function testGenerateOrganizersSitemapWithSpecialCharactersInName(): void
    {
        $organizer = $this->createMockOrganizer(
            id: 789,
            name: 'Organizer with Special <Characters> & "Quotes"',
            updatedAt: '2025-01-10 12:00:00'
        );

        $organizers = new Collection([$organizer]);
        $xml = $this->service->generateOrganizersSitemap($organizers, 'https://example.com');

        $dom = new \DOMDocument();
        $result = $dom->loadXML($xml);

        $this->assertTrue($result, 'XML with special characters should be valid');
        $this->assertStringContainsString('organizer-with-special-characters-quotes', $xml);
    }

    public function testGenerateOrganizersSitemapWithEmptySlugFallsBackToDefault(): void
    {
        $organizer = $this->createMockOrganizer(
            id: 101,
            name: '日本語名',
            updatedAt: '2025-01-10 12:00:00'
        );

        $organizers = new Collection([$organizer]);
        $xml = $this->service->generateOrganizersSitemap($organizers, 'https://example.com');

        $this->assertStringContainsString('/events/101/', $xml);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
    }

    public function testGenerateOrganizersSitemapWithEmptyCollection(): void
    {
        $organizers = new Collection([]);
        $xml = $this->service->generateOrganizersSitemap($organizers, 'https://example.com');

        $this->assertStringContainsString('urlset', $xml);
        $this->assertStringContainsString('http://www.sitemaps.org/schemas/sitemap/0.9', $xml);
        $this->assertStringNotContainsString('<url>', $xml);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
    }

    public function testGenerateOrganizersSitemapWithMultipleOrganizers(): void
    {
        $organizers = new Collection([
            $this->createMockOrganizer(1, 'Organizer One', '2025-01-10 12:00:00'),
            $this->createMockOrganizer(2, 'Organizer Two', '2025-01-11 12:00:00'),
            $this->createMockOrganizer(3, 'Organizer Three', '2024-11-15 12:00:00'),
        ]);

        $xml = $this->service->generateOrganizersSitemap($organizers, 'https://example.com');

        $this->assertStringContainsString('<loc>https://example.com/events/1/organizer-one</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/events/2/organizer-two</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/events/3/organizer-three</loc>', $xml);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $urls = $dom->getElementsByTagName('url');
        $this->assertEquals(3, $urls->length);
    }

    public function testGenerateOrganizersSitemapIncludesLastModFromUpdatedAt(): void
    {
        $organizer = $this->createMockOrganizer(
            id: 303,
            name: 'New Organizer',
            updatedAt: '2025-01-12 15:30:00'
        );

        $organizers = new Collection([$organizer]);
        $xml = $this->service->generateOrganizersSitemap($organizers, 'https://example.com');

        $this->assertStringContainsString('<lastmod>', $xml);
        $this->assertStringContainsString('2025-01-12', $xml);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
    }

    private function createMockEvent(int $id, string $title, ?string $startDate, ?string $updatedAt): EventDomainObject
    {
        $event = m::mock(EventDomainObject::class);
        $event->shouldReceive('getId')->andReturn($id);
        $event->shouldReceive('getTitle')->andReturn($title);
        $event->shouldReceive('getStartDate')->andReturn($startDate);
        $event->shouldReceive('getUpdatedAt')->andReturn($updatedAt);

        return $event;
    }

    private function createMockOrganizer(int $id, string $name, string $updatedAt): OrganizerDomainObject
    {
        $organizer = m::mock(OrganizerDomainObject::class);
        $organizer->shouldReceive('getId')->andReturn($id);
        $organizer->shouldReceive('getName')->andReturn($name);
        $organizer->shouldReceive('getUpdatedAt')->andReturn($updatedAt);

        return $organizer;
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
