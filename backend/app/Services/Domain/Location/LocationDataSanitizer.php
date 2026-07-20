<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Location;

use HiEvents\Services\Infrastructure\Geo\GeoProviderInterface;

class LocationDataSanitizer
{
    private const CONTROL_CHARACTERS_PATTERN = '/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{2028}\x{2029}]+/u';

    public function __construct(
        private readonly GeoProviderInterface $geoProvider,
    ) {}

    public function sanitizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $spaced = preg_replace(self::CONTROL_CHARACTERS_PATTERN, ' ', $value) ?? $value;

        return trim(preg_replace('/ {2,}/', ' ', $spaced) ?? $spaced);
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
