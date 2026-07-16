<?php

namespace Tests\Unit\Services\Domain\Location;

use HiEvents\Services\Domain\Location\LocationDataSanitizer;
use HiEvents\Services\Infrastructure\Geo\GeoProviderInterface;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LocationDataSanitizerTest extends TestCase
{
    private HtmlPurifierService|MockInterface $purifier;

    private GeoProviderInterface|MockInterface $geoProvider;

    private LocationDataSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purifier = Mockery::mock(HtmlPurifierService::class);
        $this->geoProvider = Mockery::mock(GeoProviderInterface::class);
        $this->sanitizer = new LocationDataSanitizer($this->purifier, $this->geoProvider);
    }

    public function test_sanitize_text_purifies_and_strips_tags(): void
    {
        $this->purifier->shouldReceive('purify')->with('<b>Venue</b>')->andReturn('<b>Venue</b>');

        $this->assertSame('Venue', $this->sanitizer->sanitizeText('<b>Venue</b>'));
    }

    public function test_sanitize_text_returns_null_for_null(): void
    {
        $this->purifier->shouldNotReceive('purify');

        $this->assertNull($this->sanitizer->sanitizeText(null));
    }

    public function test_sanitize_address_sanitizes_strings_and_uppercases_country(): void
    {
        $this->purifier->shouldReceive('purify')->andReturnUsing(fn (string $value) => $value);

        $result = $this->sanitizer->sanitizeAddress([
            'venue_name' => null,
            'city' => 'Dublin',
            'country' => 'ie',
        ]);

        $this->assertNull($result['venue_name']);
        $this->assertSame('Dublin', $result['city']);
        $this->assertSame('IE', $result['country']);
    }

    public function test_sanitize_address_without_country_key(): void
    {
        $this->purifier->shouldReceive('purify')->andReturnUsing(fn (string $value) => $value);

        $this->assertSame(['city' => 'Dublin'], $this->sanitizer->sanitizeAddress(['city' => 'Dublin']));
    }

    public function test_cached_raw_provider_response_requires_provider_and_place_id(): void
    {
        $this->geoProvider->shouldNotReceive('getCachedRawPlaceDetails');

        $this->assertNull($this->sanitizer->cachedRawProviderResponse(null, 'place_1'));
        $this->assertNull($this->sanitizer->cachedRawProviderResponse('google', null));
    }

    public function test_cached_raw_provider_response_delegates_to_provider_cache(): void
    {
        $this->geoProvider
            ->shouldReceive('getCachedRawPlaceDetails')
            ->once()
            ->with('place_1')
            ->andReturn(['id' => 'place_1']);

        $this->assertSame(['id' => 'place_1'], $this->sanitizer->cachedRawProviderResponse('google', 'place_1'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
