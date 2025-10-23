<?php

namespace HiEvents\Providers;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Models\Event;
use HiEvents\Models\Organizer;
use HiEvents\Services\Infrastructure\CurrencyConversion\CurrencyConversionClientInterface;
use HiEvents\Services\Infrastructure\CurrencyConversion\NoOpCurrencyConversionClient;
use HiEvents\Services\Infrastructure\CurrencyConversion\OpenExchangeRatesCurrencyConversionClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bindDoctrineConnection();
        $this->bindStripeServices();
        $this->bindCurrencyConversionClient();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->handleHttpsEnforcing();

        $this->handleQueryLogging();

        $this->disableLazyLoading();

        $this->registerMorphMaps();
    }

    private function bindDoctrineConnection(): void
    {
        if ($this->app->environment('production')) {
            return;
        }

        $this->app->bind(
            AbstractSchemaManager::class,
            function () {
                $config = new Configuration();

                $connectionParams = [
                    'dbname' => config('database.connections.pgsql.database'),
                    'user' => config('database.connections.pgsql.username'),
                    'password' => config('database.connections.pgsql.password'),
                    'host' => config('database.connections.pgsql.host'),
                    'driver' => 'pdo_pgsql',
                ];

                return DriverManager::getConnection($connectionParams, $config)->createSchemaManager();
            }
        );
    }

    private function bindStripeServices(): void
    {
        $this->app->singleton(StripeConfigurationService::class);
        $this->app->singleton(StripeClientFactory::class);
        
        if (!config('services.stripe.secret_key')) {
            logger()?->debug('Stripe secret key is not set in the configuration file. Payment processing will not work.');
            return;
        }

        $this->app->bind(
            StripeClient::class,
            fn() => new StripeClient(config('services.stripe.secret_key'))
        );
    }

    /**
     * @return void
     */
    private function handleQueryLogging(): void
    {
        if (env('APP_DEBUG') === true && env('APP_LOG_QUERIES') === true && !app()->isProduction()) {
            DB::listen(
                static function ($query) {
                    File::append(
                        storage_path('/logs/query.log'),
                        $query->sql . ' [' . implode(', ', $query->bindings) . ']' . PHP_EOL
                    );
                }
            );
        }
    }

    private function handleHttpsEnforcing(): void
    {
        if ($this->app->environment('local')) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
        }
    }

    private function registerMorphMaps(): void
    {
        Relation::enforceMorphMap([
            EventDomainObject::class => Event::class,
            OrganizerDomainObject::class => Organizer::class,
        ]);
    }

    private function disableLazyLoading(): void
    {
        Model::preventLazyLoading(!app()->isProduction());
    }

    private function bindCurrencyConversionClient(): void
    {
        $this->app->bind(
            CurrencyConversionClientInterface::class,
            function () {
                if (config('services.open_exchange_rates.app_id')) {
                    return new OpenExchangeRatesCurrencyConversionClient(
                        apiKey: config('services.open_exchange_rates.app_id'),
                        cache: $this->app->make('cache.store'),
                        logger: $this->app->make('log')
                    );
                }

                // Fallback to no-op client if no other client is available
                return new NoOpCurrencyConversionClient(
                    logger: $this->app->make('log')
                );
            }
        );
    }
}
