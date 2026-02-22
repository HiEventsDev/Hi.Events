<?php

namespace HiEvents\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Two\AbstractProvider;

/**
 * A specialized OIDC Provider that cleanly avoids hitting the session
 * when stateless() has been called on it, allowing use within /api routes.
 */
class StatelessOidcProvider extends \SocialiteProviders\OIDC\Provider
{
    protected function usesNonce(): bool
    {
        return !$this->stateless && $this->usesNonce;
    }

    protected function usesPKCE(): bool
    {
        return !$this->stateless && (method_exists(parent::class, 'usesPKCE') ? parent::usesPKCE() : false);
    }

    protected function usesState(): bool
    {
        return !$this->stateless && parent::usesState();
    }
}

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
            $providerUpper = strtoupper($provider);
            $driver = env("AUTH_{$providerUpper}_DRIVER", 'openid');

            // Set up config array dynamically
            $config = [
                'client_id' => env("AUTH_{$providerUpper}_CLIENT_ID"),
                'client_secret' => env("AUTH_{$providerUpper}_CLIENT_SECRET"),
                'redirect' => env("AUTH_{$providerUpper}_REDIRECT_URI", "/api/v1/auth/{$provider}/callback"),
                'identifier_key' => env("AUTH_{$providerUpper}_IDENTIFIER_KEY", 'email'),
                'issuer_url' => env("AUTH_{$providerUpper}_ISSUER_URL"),
                'base_url' => env("AUTH_{$providerUpper}_ISSUER_URL"),
                'scope' => env("AUTH_{$providerUpper}_SCOPE", 'openid email profile'),
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
        $this->app->booted(function () {
            $socialite = $this->app->make(\Laravel\Socialite\Contracts\Factory::class);

            $providersStr = env('AUTH_PROVIDERS', '');
            if (!empty($providersStr)) {
                $providers = array_filter(array_map('trim', explode(',', $providersStr)));

                foreach ($providers as $provider) {
                    $providerUpper = strtoupper($provider);
                    $driver = env("AUTH_{$providerUpper}_DRIVER", 'openid');

                    if (in_array($driver, ['openid', 'oidc'])) {
                        $socialite->extend($provider, function ($app) use ($socialite, $provider) {
                            $config = $app['config']["services.{$provider}"];
                            $instance = $socialite->buildProvider(StatelessOidcProvider::class, $config);
                            $instance->setConfig(
                                new \SocialiteProviders\Manager\Config(
                                    $config['client_id'],
                                    $config['client_secret'],
                                    $config['redirect'],
                                    $config
                                )
                            );
                            return $instance;
                        });
                    }
                }
            }
        });
    }
}
