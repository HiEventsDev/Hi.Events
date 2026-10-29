<?php

namespace HiEvents\Http\Middleware;

use HiEvents\Exceptions\ApiKeyNotAllowedException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * @throws AuthenticationException
     * @throws ApiKeyNotAllowedException
     */
    protected function unauthenticated($request, array $guards)
    {
        if ($this->carriesApiKey($request)) {
            if (! in_array('sanctum', $guards, true)) {
                throw new ApiKeyNotAllowedException;
            }

            throw new AuthenticationException(__('This API key is invalid, has expired or has been revoked.'), $guards);
        }

        parent::unauthenticated($request, $guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('auth.login');
    }

    private function carriesApiKey(Request $request): bool
    {
        $prefix = (string) config('sanctum.token_prefix');
        $token = (string) $request->bearerToken();

        return $prefix !== '' && (str_starts_with($token, $prefix) || str_contains($token, '|'.$prefix));
    }
}
