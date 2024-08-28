<?php

namespace HiEvents\Providers;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Models\Event;
use HiEvents\Models\Organizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bindDoctrineConnection();
        $this->bindStripeClient();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
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

        Model::preventLazyLoading(!app()->isProduction());

        Relation::enforceMorphMap([
            EventDomainObject::class => Event::class,
            OrganizerDomainObject::class => Organizer::class,
        ]);
    }

    private function bindDoctrineConnection(): void
    {
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

    private function bindStripeClient(): void
    {
        if (!config('services.stripe.secret_key')) {
            logger()?->debug('Stripe secret key is not set in the configuration file. Payment processing will not work.');
            return;
        }

        $this->app->bind(
            StripeClient::class,
            fn() => new StripeClient(config('services.stripe.secret_key'))
        );
    }
}
