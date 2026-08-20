<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Geo;

use HiEvents\Services\Infrastructure\Geo\DTO\GeoPlaceDTO;
use HiEvents\Services\Infrastructure\Geo\DTO\GeoSuggestionDTO;

interface GeoProviderInterface
{
    /**
     * @return array<GeoSuggestionDTO>
     */
    public function autocomplete(string $query, ?string $locale = null, ?string $country = null): array;

    public function getPlaceDetails(string $providerPlaceId, ?string $locale = null): ?GeoPlaceDTO;

    public function getCachedRawPlaceDetails(?string $providerPlaceId): ?array;

    public function isAvailable(): bool;
}
