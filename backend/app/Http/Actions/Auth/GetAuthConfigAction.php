<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetAuthConfigAction extends BaseAction
{
    public function __invoke(): JsonResponse
    {
        return $this->jsonResponse([
            'oauth_enabled' => (bool)config('services.auth.oauth_enabled'),
            'google_enabled' => (bool)config('services.auth.google_enabled'),
            'apple_enabled' => (bool)config('services.auth.apple_enabled'),
            'mfa_enabled' => (bool)config('services.auth.mfa_enabled'),
            'passkey_enabled' => (bool)config('services.auth.passkey_enabled'),
            'allowed_login_methods' => explode(',', config('services.auth.allowed_login_methods', 'password')),
            'google_client_id' => config('services.auth.google_enabled') ? config('services.google.client_id') : null,
            'apple_client_id' => config('services.auth.apple_enabled') ? config('services.apple.client_id') : null,
        ]);
    }
}
