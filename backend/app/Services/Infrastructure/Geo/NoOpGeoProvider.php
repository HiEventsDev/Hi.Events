<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Geo;

use HiEvents\Services\Infrastructure\Geo\DTO\GeoPlaceDTO;
use Psr\Log\LoggerInterface;

class NoOpGeoProvider implements GeoProviderInterface
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function autocomplete(string $query, ?string $locale = null, ?string $country = null): array
    {
        $this->logger->warning('NoOpGeoProvider in use — autocomplete returned no results. Configure a real provider in services.geo.');

        return [];
    }

    public function getPlaceDetails(string $providerPlaceId, ?string $locale = null): ?GeoPlaceDTO
    {
        $this->logger->warning('NoOpGeoProvider in use — getPlaceDetails returned null. Configure a real provider in services.geo.');

        return null;
    }

    public function getCachedRawPlaceDetails(?string $providerPlaceId): ?array
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
