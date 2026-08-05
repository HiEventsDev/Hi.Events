<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Locations;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Infrastructure\Geo\GeoProviderInterface;
use Illuminate\Http\JsonResponse;

class GetGeoStatusAction extends BaseAction
{
    public function __construct(
        private readonly GeoProviderInterface $geoProvider,
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->jsonResponse([
            'data' => [
                'available' => $this->geoProvider->isAvailable(),
            ],
        ]);
    }
}
