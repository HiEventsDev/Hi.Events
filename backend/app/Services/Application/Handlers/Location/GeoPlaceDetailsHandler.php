<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Location;

use HiEvents\Services\Infrastructure\Geo\DTO\GeoPlaceDTO;
use HiEvents\Services\Infrastructure\Geo\GeoProviderInterface;

class GeoPlaceDetailsHandler
{
    public function __construct(
        private readonly GeoProviderInterface $geoProvider,
    ) {}

    public function handle(string $providerPlaceId, ?string $locale = null): ?GeoPlaceDTO
    {
        return $this->geoProvider->getPlaceDetails($providerPlaceId, $locale);
    }
}
