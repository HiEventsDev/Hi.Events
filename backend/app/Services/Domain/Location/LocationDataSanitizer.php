<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Location;

use HiEvents\Services\Infrastructure\Geo\GeoProviderInterface;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;

class LocationDataSanitizer
{
    public function __construct(
        private readonly HtmlPurifierService $purifier,
        private readonly GeoProviderInterface $geoProvider,
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

        if (is_string($address['country'] ?? null)) {
            $address['country'] = strtoupper($address['country']);
        }

        return $address;
    }

    public function cachedRawProviderResponse(?string $provider, ?string $providerPlaceId): ?array
    {
        if ($provider === null || $providerPlaceId === null) {
            return null;
        }

        return $this->geoProvider->getCachedRawPlaceDetails($providerPlaceId);
    }
}
