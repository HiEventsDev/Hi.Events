<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Locations;

use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetGeoStatusAction extends BaseAction
{
    public function __invoke(): JsonResponse
    {
        $provider = config('services.geo.provider');
        $googleKey = config('services.geo.google.api_key');

        return $this->jsonResponse([
            'data' => [
                'available' => $provider === 'google' && ! empty($googleKey),
            ],
        ]);
    }
}
