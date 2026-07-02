<?php

namespace Tests\Unit\Services\Infrastructure\Geo;

use HiEvents\Services\Infrastructure\Geo\Exception\GeoProviderException;
use HiEvents\Services\Infrastructure\Geo\Exception\GeoProviderQuotaExceededException;
use HiEvents\Services\Infrastructure\Geo\GooglePlacesGeoProvider;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\TestCase;

class GooglePlacesGeoProviderTest extends TestCase
{
    private LoggerInterface $logger;

    private CacheRepository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new NullLogger;
        $this->cache = new CacheRepository(new ArrayStore);
    }

    private function makeProvider(): GooglePlacesGeoProvider
    {
        return new GooglePlacesGeoProvider('test-key', app(HttpClient::class), $this->logger, $this->cache);
    }

    public function test_autocomplete_maps_response_to_suggestion_dtos(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places:autocomplete' => Http::response([
                'suggestions' => [
                    [
                        'placePrediction' => [
                            'placeId' => 'ChIJ123',
                            'structuredFormat' => [
                                'mainText' => ['text' => 'Some Venue'],
                                'secondaryText' => ['text' => 'Dublin, Ireland'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $provider = $this->makeProvider();
        $results = $provider->autocomplete('some venue', locale: 'en', country: 'IE');

        $this->assertCount(1, $results);
        $this->assertSame('ChIJ123', $results[0]->provider_place_id);
        $this->assertSame('Some Venue', $results[0]->primary_text);
        $this->assertSame('Dublin, Ireland', $results[0]->secondary_text);
    }

    public function test_autocomplete_returns_empty_on_blank_query(): void
    {
        $provider = $this->makeProvider();
        $this->assertSame([], $provider->autocomplete('   '));
    }

    public function test_get_place_details_maps_establishment_to_address_dto(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places/*' => Http::response([
                'id' => 'ChIJ123',
                'formattedAddress' => '3 Arena, North Wall Quay, Dublin 1, Ireland',
                'displayName' => ['text' => '3 Arena'],
                'types' => ['establishment', 'point_of_interest'],
                'location' => ['latitude' => 53.3478, 'longitude' => -6.2289],
                'addressComponents' => [
                    ['types' => ['street_number'], 'shortText' => '3', 'longText' => '3'],
                    ['types' => ['route'], 'shortText' => 'North Wall Quay', 'longText' => 'North Wall Quay'],
                    ['types' => ['locality'], 'shortText' => 'Dublin', 'longText' => 'Dublin'],
                    ['types' => ['administrative_area_level_1'], 'shortText' => 'Dublin 1', 'longText' => 'Dublin 1'],
                    ['types' => ['postal_code'], 'shortText' => 'D01 T0X4', 'longText' => 'D01 T0X4'],
                    ['types' => ['country'], 'shortText' => 'IE', 'longText' => 'Ireland'],
                ],
            ], 200),
        ]);

        $provider = $this->makeProvider();
        $place = $provider->getPlaceDetails('ChIJ123');

        $this->assertNotNull($place);
        $this->assertSame('google', $place->provider);
        $this->assertSame('ChIJ123', $place->provider_place_id);
        $this->assertSame('3 Arena', $place->address->venue_name);
        $this->assertSame('3 North Wall Quay', $place->address->address_line_1);
        $this->assertSame('Dublin', $place->address->city);
        $this->assertSame('Dublin 1', $place->address->state_or_region);
        $this->assertSame('D01 T0X4', $place->address->zip_or_postal_code);
        $this->assertSame('IE', $place->address->country);
        $this->assertEqualsWithDelta(53.3478, $place->latitude, 0.0001);
        $this->assertEqualsWithDelta(-6.2289, $place->longitude, 0.0001);
    }

    public function test_get_place_details_skips_venue_name_for_street_address(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places/*' => Http::response([
                'id' => 'ChIJ456',
                'displayName' => ['text' => '123 Main St'],
                'types' => ['street_address'],
                'addressComponents' => [
                    ['types' => ['street_number'], 'shortText' => '123', 'longText' => '123'],
                    ['types' => ['route'], 'shortText' => 'Main St', 'longText' => 'Main Street'],
                    ['types' => ['locality'], 'shortText' => 'Springfield', 'longText' => 'Springfield'],
                    ['types' => ['country'], 'shortText' => 'US', 'longText' => 'United States'],
                ],
            ], 200),
        ]);

        $provider = $this->makeProvider();
        $place = $provider->getPlaceDetails('ChIJ456');

        $this->assertNotNull($place);
        $this->assertNull($place->address->venue_name);
        $this->assertSame('123 Main St', $place->address->address_line_1);
    }

    public function test_get_place_details_returns_null_on_404(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places/*' => Http::response(['error' => 'not found'], 404),
        ]);

        $provider = $this->makeProvider();
        $this->assertNull($provider->getPlaceDetails('ChIJ-bad'));
    }

    public function test_get_place_details_throws_on_5xx(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places/*' => Http::response(['error' => 'boom'], 503),
        ]);

        $provider = $this->makeProvider();

        $this->expectException(GeoProviderException::class);
        $provider->getPlaceDetails('ChIJ-503');
    }

    public function test_autocomplete_throws_on_5xx(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places:autocomplete' => Http::response(['error' => 'boom'], 502),
        ]);

        $provider = $this->makeProvider();

        $this->expectException(GeoProviderException::class);
        $provider->autocomplete('something');
    }

    public function test_autocomplete_throws_quota_exception_on_429(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places:autocomplete' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429),
        ]);

        $provider = $this->makeProvider();

        $this->expectException(GeoProviderQuotaExceededException::class);
        $provider->autocomplete('something');
    }

    public function test_get_place_details_caches_responses(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places/*' => Http::response([
                'id' => 'ChIJ-cached',
                'displayName' => ['text' => 'Cached Place'],
                'types' => ['establishment'],
                'addressComponents' => [
                    ['types' => ['country'], 'shortText' => 'IE', 'longText' => 'Ireland'],
                ],
            ], 200),
        ]);

        $provider = $this->makeProvider();

        $first = $provider->getPlaceDetails('ChIJ-cached');
        $second = $provider->getPlaceDetails('ChIJ-cached');

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        // One upstream call, not two — the second resolves from cache.
        Http::assertSentCount(1);
    }
}
