<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Sitemap;

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
        $this->service = new SitemapGeneratorService;
    }

    public function test_generate_sitemap_index_with_single_page(): void
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

    public function test_generate_sitemap_index_with_multiple_pages(): void
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

    public function test_generate_sitemap_index_is_valid_xml(): void
    {
        $xml = $this->service->generateSitemapIndex(2, 1, 'https://example.com', '2025-01-15T10:30:00+00:00');

        $dom = new \DOMDocument;
        $result = $dom->loadXML($xml);

        $this->assertTrue($result, 'Generated XML should be valid');
    }

    public function test_generate_events_sitemap_with_event(): void
    {
        $event = $this->createMockEvent(
            id: 123,
            title: 'My Amazing Event',
            updatedAt: '2025-01-10 12:00:00'
        );

        $events = new Collection([$event]);
        $baseUrl = 'https://example.com';

        $xml = $this->service->generateEventsSitemap($events, $baseUrl);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringContainsString('<loc>https://example.com/event/123/my-amazing-event</loc>', $xml);
        $this->assertStringNotContainsString('<changefreq>', $xml);
        $this->assertStringNotContainsString('<priority>', $xml);
    }

    public function test_generate_events_sitemap_with_special_characters_in_title(): void
    {
        $event = $this->createMockEvent(
            id: 789,
            title: 'Event with Special <Characters> & "Quotes"',
            updatedAt: '2025-01-10 12:00:00'
        );

        $events = new Collection([$event]);
        $xml = $this->service->generateEventsSitemap($events, 'https://example.com');

        $dom = new \DOMDocument;
        $result = $dom->loadXML($xml);

        $this->assertTrue($result, 'XML with special characters should be valid');
        $this->assertStringContainsString('event-with-special-characters-quotes', $xml);
    }

    public function test_generate_events_sitemap_with_empty_slug_falls_back_to_default(): void
    {
        $event = $this->createMockEvent(
            id: 101,
            title: '日本語タイトル',
            updatedAt: '2025-01-10 12:00:00'
        );

        $events = new Collection([$event]);
        $xml = $this->service->generateEventsSitemap($events, 'https://example.com');

        $this->assertStringContainsString('/event/101/', $xml);

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml));
    }

    public function test_generate_events_sitemap_includes_last_mod_from_updated_at(): void
    {
        $event = $this->createMockEvent(
            id: 303,
            title: 'New Event',
            updatedAt: '2025-01-12 15:30:00'
        );

        $events = new Collection([$event]);
        $xml = $this->service->generateEventsSitemap($events, 'https://example.com');

        $this->assertStringContainsString('<lastmod>', $xml);
        $this->assertStringContainsString('2025-01-12', $xml);

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml));
    }

    public function test_generate_events_sitemap_with_empty_collection(): void
    {
        $events = new Collection([]);
        $xml = $this->service->generateEventsSitemap($events, 'https://example.com');

        $this->assertStringContainsString('urlset', $xml);
        $this->assertStringContainsString('http://www.sitemaps.org/schemas/sitemap/0.9', $xml);
        $this->assertStringNotContainsString('<url>', $xml);

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml));
    }

    public function test_generate_events_sitemap_with_multiple_events(): void
    {
        $events = new Collection([
            $this->createMockEvent(1, 'Event One', '2025-01-10 12:00:00'),
            $this->createMockEvent(2, 'Event Two', '2025-01-11 12:00:00'),
            $this->createMockEvent(3, 'Event Three', '2024-11-15 12:00:00'),
        ]);

        $xml = $this->service->generateEventsSitemap($events, 'https://example.com');

        $this->assertStringContainsString('<loc>https://example.com/event/1/event-one</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/event/2/event-two</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/event/3/event-three</loc>', $xml);

        $dom = new \DOMDocument;
        $dom->loadXML($xml);
        $urls = $dom->getElementsByTagName('url');
        $this->assertEquals(3, $urls->length);
    }

    public function test_generate_organizers_sitemap_with_organizer(): void
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
        $this->assertStringNotContainsString('<changefreq>', $xml);
        $this->assertStringNotContainsString('<priority>', $xml);
    }

    public function test_generate_organizers_sitemap_with_special_characters_in_name(): void
    {
        $organizer = $this->createMockOrganizer(
            id: 789,
            name: 'Organizer with Special <Characters> & "Quotes"',
            updatedAt: '2025-01-10 12:00:00'
        );

        $organizers = new Collection([$organizer]);
        $xml = $this->service->generateOrganizersSitemap($organizers, 'https://example.com');

        $dom = new \DOMDocument;
        $result = $dom->loadXML($xml);

        $this->assertTrue($result, 'XML with special characters should be valid');
        $this->assertStringContainsString('organizer-with-special-characters-quotes', $xml);
    }

    public function test_generate_organizers_sitemap_with_empty_slug_falls_back_to_default(): void
    {
        $organizer = $this->createMockOrganizer(
            id: 101,
            name: '日本語名',
            updatedAt: '2025-01-10 12:00:00'
        );

        $organizers = new Collection([$organizer]);
        $xml = $this->service->generateOrganizersSitemap($organizers, 'https://example.com');

        $this->assertStringContainsString('/events/101/', $xml);

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml));
    }

    public function test_generate_organizers_sitemap_with_empty_collection(): void
    {
        $organizers = new Collection([]);
        $xml = $this->service->generateOrganizersSitemap($organizers, 'https://example.com');

        $this->assertStringContainsString('urlset', $xml);
        $this->assertStringContainsString('http://www.sitemaps.org/schemas/sitemap/0.9', $xml);
        $this->assertStringNotContainsString('<url>', $xml);

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml));
    }

    public function test_generate_organizers_sitemap_with_multiple_organizers(): void
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

        $dom = new \DOMDocument;
        $dom->loadXML($xml);
        $urls = $dom->getElementsByTagName('url');
        $this->assertEquals(3, $urls->length);
    }

    public function test_generate_organizers_sitemap_includes_last_mod_from_updated_at(): void
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

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml));
    }

    private function createMockEvent(int $id, string $title, string $updatedAt): EventDomainObject
    {
        $event = m::mock(EventDomainObject::class);
        $event->shouldReceive('getId')->andReturn($id);
        $event->shouldReceive('getTitle')->andReturn($title);
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
