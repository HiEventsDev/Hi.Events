<?php

namespace Tests\Unit\Services\Domain\Location;

use HiEvents\Services\Domain\Location\LocationDataSanitizer;
use HiEvents\Services\Infrastructure\Geo\GeoProviderInterface;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LocationDataSanitizerTest extends TestCase
{
    private GeoProviderInterface|MockInterface $geoProvider;

    private LocationDataSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->geoProvider = Mockery::mock(GeoProviderInterface::class);
        $this->sanitizer = new LocationDataSanitizer($this->geoProvider);
    }

    public function test_sanitize_text_preserves_ampersands_and_special_characters(): void
    {
        $this->assertSame('Barnes & Noble', $this->sanitizer->sanitizeText('Barnes & Noble'));
        $this->assertSame("O'Reilly's \"Pub\"", $this->sanitizer->sanitizeText("O'Reilly's \"Pub\""));
    }

    public function test_sanitize_text_preserves_angle_brackets_and_markup(): void
    {
        $this->assertSame('I <3 NY', $this->sanitizer->sanitizeText('I <3 NY'));
        $this->assertSame('Bar <Grill', $this->sanitizer->sanitizeText('Bar <Grill'));
        $this->assertSame('<b>x</b>', $this->sanitizer->sanitizeText('<b>x</b>'));
        $this->assertSame('<script>alert(1)</script>Cafe', $this->sanitizer->sanitizeText('<script>alert(1)</script>Cafe'));
    }

    public function test_sanitize_text_replaces_control_characters_with_single_space(): void
    {
        $this->assertSame('Ve nue', $this->sanitizer->sanitizeText("Ve\x00nue\x1F"));
        $this->assertSame('Line1 Line2', $this->sanitizer->sanitizeText("Line1\nLine2"));
        $this->assertSame('Line1 Line2', $this->sanitizer->sanitizeText("Line1\r\n\tLine2"));
        $this->assertSame('Line1 Line2', $this->sanitizer->sanitizeText("Line1 \n Line2"));
    }

    public function test_sanitize_text_returns_null_for_null(): void
    {
        $this->assertNull($this->sanitizer->sanitizeText(null));
    }

    public function test_sanitize_address_sanitizes_strings_and_uppercases_country(): void
    {
        $result = $this->sanitizer->sanitizeAddress([
            'venue_name' => 'Tom & Jerry',
            'city' => 'Dublin',
            'country' => 'ie',
        ]);

        $this->assertSame('Tom & Jerry', $result['venue_name']);
        $this->assertSame('Dublin', $result['city']);
        $this->assertSame('IE', $result['country']);
    }

    public function test_sanitize_address_without_country_key(): void
    {
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
