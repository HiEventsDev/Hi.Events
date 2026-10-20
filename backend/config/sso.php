<?php

return [
    'password_login_disabled' => (bool) env('SSO_PASSWORD_LOGIN_DISABLED', false),

    'allow_private_issuers' => (bool) env('SSO_ALLOW_PRIVATE_ISSUERS', false),

    'providers' => [
        'google' => [
            'type' => 'GOOGLE',
            'name' => 'Google',
            'issuer' => 'https://accounts.google.com',
            'client_id' => env('SSO_GOOGLE_CLIENT_ID'),
            'client_secret' => env('SSO_GOOGLE_CLIENT_SECRET'),
        ],
        'microsoft' => [
            'type' => 'MICROSOFT',
            'name' => 'Microsoft',
            'issuer' => 'https://login.microsoftonline.com/common/v2.0',
            'client_id' => env('SSO_MICROSOFT_CLIENT_ID'),
            'client_secret' => env('SSO_MICROSOFT_CLIENT_SECRET'),
        ],
        'oidc' => [
            'type' => 'OIDC',
            'name' => env('SSO_OIDC_NAME') ?: 'SSO',
            'issuer' => env('SSO_OIDC_ISSUER'),
            'client_id' => env('SSO_OIDC_CLIENT_ID'),
            'client_secret' => env('SSO_OIDC_CLIENT_SECRET'),
            'logo_url' => env('SSO_OIDC_LOGO_URL'),
            'trust_email' => (bool) env('SSO_OIDC_TRUST_EMAIL', false),
        ],
    ],
];
