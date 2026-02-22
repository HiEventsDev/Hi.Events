<?php

namespace HiEvents\Http\Actions\Auth;

use Illuminate\Http\JsonResponse;

class GetAuthConfigAction extends BaseAuthAction
{
    public function __invoke(): JsonResponse
    {
        $providersStr = env('AUTH_PROVIDERS', '');
        $providerKeys = empty($providersStr)
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $providersStr))));

        $providers = array_map(function ($key) {
            $upperKey = strtoupper($key);
            return [
                'id' => $key,
                'name' => ucfirst($key),
                'logo_url' => env("AUTH_{$upperKey}_LOGO_URL", null),
            ];
        }, $providerKeys);

        return response()->json([
            'auth_disable_default' => env('AUTH_DISABLE_DEFAULT', false) === 'true' || env('AUTH_DISABLE_DEFAULT', false) === true,
            'auth_providers' => $providers
        ]);
    }
}
