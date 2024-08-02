<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [
    'name' => env('APP_NAME', 'Hi.Events'),

    'reset_password_token_expiry_in_min' => 15,
    'frontend_url' => env('APP_FRONTEND_URL', 'http://localhost'),
    'cnd_url' => env('APP_CDN_URL', '/storage'),
    'default_timezone' => 'America/Vancouver',
    'default_currency_code' => 'USD',
    'saas_mode_enabled' => env('APP_SAAS_MODE_ENABLED', false),
    'saas_stripe_application_fee_percent' => env('APP_SAAS_STRIPE_APPLICATION_FEE_PERCENT', 1.5),
    'disable_registration' => env('APP_DISABLE_REGISTRATION', false),

    /**
     * The number of page views to batch before updating the database
     *
     * For high traffic sites, this can be set to a higher number to reduce the number of database writes
     */
    'homepage_views_update_batch_size' => env('APP_HOMEPAGE_VIEWS_UPDATE_BATCH_SIZE', 8),

    /**
     * The number of seconds to cache the ticket quantities on the homepage
     * It is recommended to cache this value for a short period of time for high traffic sites
     *
     * Set to null to disable caching
     */
    'homepage_ticket_quantities_cache_ttl' => env('APP_HOMEPAGE_TICKET_QUANTITIES_CACHE_TTL', 2),

    'frontend_urls' => [
        'confirm_email_address' => '/manage/profile/confirm-email-address/%s',
        'reset_password' => '/auth/reset-password/%s',
        'confirm_email_change' => '/manage/profile/confirm-email-change/%s',
        'accept_invitation' => '/auth/accept-invitation/%s',
        'stripe_connect_return_url' => '/account/payment',
        'stripe_connect_refresh_url' => '/account/payment',
        'event_homepage' => '/event/%d/%s',
        'attendee_ticket' => '/ticket/%d/%s',
        'order_summary' => '/checkout/%d/%s/summary',
        'organizer_order_summary' => '/manage/event/%d/orders#order-%d',
    ],

    'email_logo_url' => env('APP_EMAIL_LOGO_URL'),
    'email_footer_text' => env('APP_EMAIL_FOOTER_TEXT'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool)env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost:5173'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store'  => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        \HiEvents\Providers\AppServiceProvider::class,
        \HiEvents\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        \HiEvents\Providers\EventServiceProvider::class,
        \HiEvents\Providers\RouteServiceProvider::class,
        \HiEvents\Providers\RepositoryServiceProvider::class

    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'Example' => App\Facades\Example::class,
    ])->toArray(),

];
