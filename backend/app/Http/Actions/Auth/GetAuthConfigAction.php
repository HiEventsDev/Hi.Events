<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAuthAction;
use Illuminate\Http\JsonResponse;

class GetAuthConfigAction extends BaseAuthAction
{
    public function __invoke(): JsonResponse
    {
        $providersStr = env('AUTH_PROVIDERS', '');
        $providers = empty($providersStr)
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $providersStr))));

        return response()->json([
            'auth_disable_default' => env('AUTH_DISABLE_DEFAULT', false) === 'true' || env('AUTH_DISABLE_DEFAULT', false) === true,
            'auth_providers' => $providers
        ]);
    }
}
