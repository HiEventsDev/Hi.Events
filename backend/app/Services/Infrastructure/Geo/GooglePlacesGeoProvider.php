<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Geo;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\Services\Infrastructure\Geo\DTO\GeoPlaceDTO;
use HiEvents\Services\Infrastructure\Geo\DTO\GeoSuggestionDTO;
use HiEvents\Services\Infrastructure\Geo\Exception\GeoProviderException;
use HiEvents\Services\Infrastructure\Geo\Exception\GeoProviderQuotaExceededException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpClient;
use Psr\Log\LoggerInterface;
use Throwable;

class GooglePlacesGeoProvider implements GeoProviderInterface
{
    public const PROVIDER_NAME = 'google';

    private const AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';

    private const PLACE_DETAILS_URL = 'https://places.googleapis.com/v1/places/';

    private const AUTOCOMPLETE_FIELD_MASK = 'suggestions.placePrediction.placeId,suggestions.placePrediction.text,suggestions.placePrediction.structuredFormat';

    private const PLACE_DETAILS_FIELD_MASK = 'id,formattedAddress,addressComponents,location,displayName,types';

    private const REQUEST_TIMEOUT_SECONDS = 8;

    private const PLACE_DETAILS_CACHE_TTL_SECONDS = 86400 * 7;

    public function __construct(
        private readonly string $apiKey,
        private readonly HttpClient $http,
        private readonly LoggerInterface $logger,
        private readonly CacheRepository $cache,
    ) {}

    public function autocomplete(string $query, ?string $locale = null, ?string $country = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $body = ['input' => $query];
        if ($locale !== null && $locale !== '') {
            $body['languageCode'] = $locale;
        }
        if ($country !== null && $country !== '') {
            $body['regionCode'] = strtoupper($country);
            $body['includedRegionCodes'] = [strtoupper($country)];
        }

        try {
            $response = $this->http
                ->withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => self::AUTOCOMPLETE_FIELD_MASK,
                ])
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->post(self::AUTOCOMPLETE_URL, $body);
        } catch (Throwable $e) {
            $this->logger->error('Google Places autocomplete failed', ['error' => $e->getMessage()]);

            throw new GeoProviderException('Geo provider unavailable', previous: $e);
        }

        if (! $response->successful()) {
            $this->logger->error('Google Places autocomplete non-2xx', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($this->isQuotaResponse($response->status(), $response->json())) {
                throw new GeoProviderQuotaExceededException(
                    sprintf('Geo provider quota exceeded (HTTP %d)', $response->status()),
                );
            }

            throw new GeoProviderException(sprintf('Geo provider returned HTTP %d', $response->status()));
        }

        $suggestions = [];
        foreach ($response->json('suggestions') ?? [] as $suggestion) {
            $prediction = $suggestion['placePrediction'] ?? null;
            if ($prediction === null) {
                continue;
            }

            $suggestions[] = new GeoSuggestionDTO(
                provider_place_id: (string) ($prediction['placeId'] ?? ''),
                primary_text: (string) ($prediction['structuredFormat']['mainText']['text'] ?? $prediction['text']['text'] ?? ''),
                secondary_text: $prediction['structuredFormat']['secondaryText']['text'] ?? null,
            );
        }

        return $suggestions;
    }

    public function getPlaceDetails(string $providerPlaceId, ?string $locale = null): ?GeoPlaceDTO
    {
        if ($providerPlaceId === '') {
            return null;
        }

        $cacheKey = $this->placeDetailsCacheKey($providerPlaceId, $locale);
        $payload = $this->cache->get($cacheKey);

        if ($payload === null) {
            $payload = $this->fetchPlaceDetailsPayload($providerPlaceId, $locale);
            if ($payload === null) {
                return null;
            }
            $this->cache->put($cacheKey, $payload, self::PLACE_DETAILS_CACHE_TTL_SECONDS);
        }

        $this->cache->put($this->rawPlaceDetailsCacheKey($providerPlaceId), $payload, self::PLACE_DETAILS_CACHE_TTL_SECONDS);

        return $this->mapToGeoPlaceDTO($payload, $providerPlaceId);
    }

    public function getCachedRawPlaceDetails(?string $providerPlaceId): ?array
    {
        if ($providerPlaceId === null || $providerPlaceId === '') {
            return null;
        }

        $payload = $this->cache->get($this->rawPlaceDetailsCacheKey($providerPlaceId));

        return is_array($payload) ? $payload : null;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    private function fetchPlaceDetailsPayload(string $providerPlaceId, ?string $locale): ?array
    {
        $url = self::PLACE_DETAILS_URL.rawurlencode($providerPlaceId);

        try {
            $query = [];
            if ($locale !== null && $locale !== '') {
                $query['languageCode'] = $locale;
            }

            $response = $this->http
                ->withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => self::PLACE_DETAILS_FIELD_MASK,
                ])
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->get($url, $query);
        } catch (Throwable $e) {
            $this->logger->error('Google Places details failed', ['error' => $e->getMessage(), 'place_id' => $providerPlaceId]);

            throw new GeoProviderException('Geo provider unavailable', previous: $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            $this->logger->error('Google Places details non-2xx', [
                'status' => $response->status(),
                'place_id' => $providerPlaceId,
                'body' => $response->body(),
            ]);

            if ($this->isQuotaResponse($response->status(), $response->json())) {
                throw new GeoProviderQuotaExceededException(
                    sprintf('Geo provider quota exceeded (HTTP %d)', $response->status()),
                );
            }

            throw new GeoProviderException(sprintf('Geo provider returned HTTP %d', $response->status()));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return null;
        }

        return $payload;
    }

    private function placeDetailsCacheKey(string $providerPlaceId, ?string $locale): string
    {
        return 'geo:place_details:'.self::PROVIDER_NAME.':'.$providerPlaceId.':'.($locale ?? '');
    }

    private function rawPlaceDetailsCacheKey(string $providerPlaceId): string
    {
        return 'geo:place_details_raw:'.self::PROVIDER_NAME.':'.$providerPlaceId;
    }

    private function isQuotaResponse(int $status, mixed $body): bool
    {
        if ($status === 429) {
            return true;
        }

        if (is_array($body)) {
            $errorStatus = $body['error']['status'] ?? null;

            return $errorStatus === 'RESOURCE_EXHAUSTED';
        }

        return false;
    }

    private function mapToGeoPlaceDTO(array $payload, string $fallbackPlaceId): GeoPlaceDTO
    {
        $components = $this->indexAddressComponents($payload['addressComponents'] ?? []);

        $streetNumber = $components['street_number']['short'] ?? null;
        $route = $components['route']['short'] ?? null;
        $addressLine1 = trim(implode(' ', array_filter([$streetNumber, $route])));

        $city = $components['locality']['short']
            ?? $components['postal_town']['short']
            ?? $components['sublocality_level_1']['short']
            ?? null;

        $stateOrRegion = $components['administrative_area_level_1']['long']
            ?? $components['administrative_area_level_1']['short']
            ?? null;
        $postalCode = $components['postal_code']['short'] ?? null;
        $country = $components['country']['short'] ?? null;
        $addressLine2 = $components['subpremise']['short'] ?? null;

        $types = $payload['types'] ?? [];
        $isEstablishment = in_array('establishment', $types, true) || in_array('point_of_interest', $types, true);
        $displayName = $payload['displayName']['text'] ?? null;
        $venueName = ($isEstablishment && $displayName) ? $displayName : null;

        $address = new AddressDTO(
            venue_name: $venueName,
            address_line_1: $addressLine1 !== '' ? $addressLine1 : null,
            address_line_2: $addressLine2,
            city: $city,
            state_or_region: $stateOrRegion,
            zip_or_postal_code: $postalCode,
            country: $country !== null ? strtoupper($country) : null,
        );

        $latitude = isset($payload['location']['latitude']) ? (float) $payload['location']['latitude'] : null;
        $longitude = isset($payload['location']['longitude']) ? (float) $payload['location']['longitude'] : null;

        return new GeoPlaceDTO(
            provider: self::PROVIDER_NAME,
            provider_place_id: (string) ($payload['id'] ?? $fallbackPlaceId),
            address: $address,
            latitude: $latitude,
            longitude: $longitude,
            display_name: $displayName,
            raw_response: $payload,
        );
    }

    private function indexAddressComponents(array $components): array
    {
        $index = [];
        foreach ($components as $component) {
            foreach ($component['types'] ?? [] as $type) {
                $index[$type] = [
                    'short' => $component['shortText'] ?? $component['longText'] ?? null,
                    'long' => $component['longText'] ?? $component['shortText'] ?? null,
                ];
            }
        }

        return $index;
    }
}
