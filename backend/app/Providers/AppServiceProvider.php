<?php

namespace TicketKitten\Providers;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\OrganizerDomainObject;
use TicketKitten\Models\Event;
use TicketKitten\Models\Organizer;

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
        if (env('APP_DEBUG') === true) {
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

                return DriverManager::getConnection($connectionParams, $config)->getSchemaManager();
            }
        );
    }

    private function bindStripeClient(): void
    {
        $this->app->bind(
            StripeClient::class,
            fn() => new StripeClient(config('services.stripe.secret_key'))
        );
    }
}
