<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Location;

use HiEvents\Services\Infrastructure\Geo\DTO\GeoSuggestionDTO;
use HiEvents\Services\Infrastructure\Geo\GeoProviderInterface;

class GeoAutocompleteHandler
{
    public function __construct(
        private readonly GeoProviderInterface $geoProvider,
    ) {}

    /**
     * @return array<GeoSuggestionDTO>
     */
    public function handle(string $query, ?string $locale = null, ?string $country = null): array
    {
        return $this->geoProvider->autocomplete($query, $locale, $country);
    }
}
