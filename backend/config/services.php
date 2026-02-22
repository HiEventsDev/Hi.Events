<?php

$services = [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'public_key' => env('STRIPE_PUBLIC_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

        // Canadian platform (Optional)
        'ca_secret_key' => env('STRIPE_CA_SECRET_KEY', env('STRIPE_SECRET_KEY')),
        'ca_public_key' => env('STRIPE_CA_PUBLIC_KEY', env('STRIPE_PUBLIC_KEY')),
        'ca_webhook_secret' => env('STRIPE_CA_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET')),

        // Irish platform (Optional)
        'ie_secret_key' => env('STRIPE_IE_SECRET_KEY', env('STRIPE_SECRET_KEY')),
        'ie_public_key' => env('STRIPE_IE_PUBLIC_KEY', env('STRIPE_PUBLIC_KEY')),
        'ie_webhook_secret' => env('STRIPE_IE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET')),

        // Primary platform for new organizers
        'primary_platform' => env('STRIPE_PRIMARY_PLATFORM'),
    ],
    'open_exchange_rates' => [
        'app_id' => env('OPEN_EXCHANGE_RATES_APP_ID'),
    ],
];

// OIDC Dynamic Providers Config Generation
$providersStr = env('AUTH_PROVIDERS');
$services['auth_providers_list'] = [];

if ($providersStr) {
    $providers = array_filter(array_map('trim', explode(',', $providersStr)));
    $services['auth_providers_list'] = $providers;

    foreach ($providers as $provider) {
        $providerUpper = strtoupper($provider);
        $services[$provider] = [
            'driver' => env("AUTH_{$providerUpper}_DRIVER", 'openid'),
            'client_id' => env("AUTH_{$providerUpper}_CLIENT_ID"),
            'client_secret' => env("AUTH_{$providerUpper}_CLIENT_SECRET"),
            'identifier_key' => env("AUTH_{$providerUpper}_IDENTIFIER_KEY", 'email'),
            'issuer_url' => env("AUTH_{$providerUpper}_ISSUER_URL"),
            'base_url' => env("AUTH_{$providerUpper}_ISSUER_URL"),
            'scope' => env("AUTH_{$providerUpper}_SCOPE", 'openid email profile'),
        ];
    }
}

return $services;
