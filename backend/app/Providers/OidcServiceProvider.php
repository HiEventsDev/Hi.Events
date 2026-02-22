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
        // Provider config array injection is now handled fully in config/services.php 
        // to support php artisan config:cache on Vapor arrays.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $socialite = $this->app->make(\Laravel\Socialite\Contracts\Factory::class);

            $providers = config('services.auth_providers_list', []);

            foreach ($providers as $provider) {
                // Config arrays are mapped and hydrated safely via config/services.php
                $config = config("services.{$provider}");
                $driver = $config['driver'] ?? 'openid';

                if (in_array($driver, ['openid', 'oidc'])) {
                    $socialite->extend($provider, function ($app) use ($socialite, $provider) {
                        $config = $app['config']["services.{$provider}"];
                        $config['redirect'] = route('auth.provider.callback', ['provider' => strtolower($provider)]);
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
        });
    }
}
