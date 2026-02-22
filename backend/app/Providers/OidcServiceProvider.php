<?php

namespace HiEvents\Providers;

use Illuminate\Support\ServiceProvider;

class OidcServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Get the list of providers
        $providersStr = env('AUTH_PROVIDERS', '');
        if (empty($providersStr)) {
            return;
        }

        $providers = array_filter(array_map('trim', explode(',', $providersStr)));

        foreach ($providers as $provider) {
            $driver = env("AUTH_{$provider}_DRIVER", 'openid');

            // Set up config array dynamically
            $config = [
                'client_id' => env("AUTH_{$provider}_CLIENT_ID"),
                'client_secret' => env("AUTH_{$provider}_CLIENT_SECRET"),
                'redirect' => env("AUTH_{$provider}_REDIRECT_URI", "/api/v1/auth/{$provider}/callback"),
                'identifier_key' => env("AUTH_{$provider}_IDENTIFIER_KEY", 'email'),
                'issuer_url' => env("AUTH_{$provider}_ISSUER_URL"),
                'scope' => env("AUTH_{$provider}_SCOPE", 'openid email profile'),
            ];

            // Some specific drivers might need different structures, we support openid by mapping it to 'oidc' if needed,
            // or we just inject it into the service configuration array natively.
            config(["services.{$provider}" => $config]);

            // We explicitly inform Socialite if using socialiteproviders
            // We usually set config['services.something'] which Socialite picks up automatically.
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Also register the Socialite event listener for OpenID connect
        // if using SocialiteProviders/Manager.
        $events = $this->app['events'];
        $events->listen(\SocialiteProviders\Manager\SocialiteWasCalled::class, function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('openid', \SocialiteProviders\Oidc\Provider::class);
            $event->extendSocialite('oidc', \SocialiteProviders\Oidc\Provider::class);
        });
    }
}
