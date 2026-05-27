<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Location;

use HiEvents\Services\Infrastructure\Geo\Exception\GeoProviderException;
use HiEvents\Services\Infrastructure\Geo\GeoProviderInterface;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Psr\Log\LoggerInterface;

/**
 * Shared sanitisation + provider-lookup helpers used by both
 * CreateLocationHandler and UpdateLocationHandler. Keeps the write-path
 * handlers single-responsibility (persistence) without duplicating the
 * HTML purify / geo-fetch logic across two near-identical files.
 */
class LocationDataSanitizer
{
    public function __construct(
        private readonly HtmlPurifierService $purifier,
        private readonly GeoProviderInterface $geoProvider,
        private readonly LoggerInterface $logger,
    ) {}

    public function sanitizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strip_tags($this->purifier->purify($value));
    }

    public function sanitizeAddress(array $address): array
    {
        foreach ($address as $key => $value) {
            if (is_string($value)) {
                $address[$key] = $this->sanitizeText($value);
            }
        }

        return $address;
    }

    /**
     * Returns the provider's raw place response so we have a record of
     * exactly what we saved this location from. Failures degrade to null
     * — saving must not block on a flaky third party.
     */
    public function fetchRawProviderResponse(?string $provider, ?string $providerPlaceId): ?array
    {
        if ($provider === null || $providerPlaceId === null) {
            return null;
        }

        try {
            return $this->geoProvider->getPlaceDetails($providerPlaceId)?->raw_response;
        } catch (GeoProviderException $e) {
            $this->logger->warning('Geo provider lookup failed during location save; storing without raw response', [
                'provider' => $provider,
                'provider_place_id' => $providerPlaceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
