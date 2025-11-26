<?php

return [
    'enabled' => env('OIDC_ENABLED', false),
    'issuer' => env('OIDC_ISSUER'),
    'client_id' => env('OIDC_CLIENT_ID'),
    'client_secret' => env('OIDC_CLIENT_SECRET'),
    'redirect_uri' => env('OIDC_REDIRECT_URI', rtrim(env('APP_URL', ''), '/') . '/api/auth/oidc/callback'),
    'scopes' => explode(' ', env('OIDC_SCOPES', 'openid email profile')),
    'logout_redirect_uri' => env('OIDC_LOGOUT_REDIRECT_URI', env('APP_FRONTEND_URL', null)),

    /*
     * Optional audience override (some providers require api identifier)
     */
    'audience' => env('OIDC_AUDIENCE'),

    /*
     * Cache JWKS for this many seconds
     */
    'jwks_cache_ttl' => env('OIDC_JWKS_CACHE_TTL', 300),
];
