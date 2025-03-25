<?php

namespace HiEvents\Providers;

use HiEvents\Listeners\Webhook\WebhookEventListener;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\CheckinEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\ProductEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Map of listeners to the events they should handle.
     *
     * @var array<class-string, array<class-string>>
     */
    private static array $domainEventMap = [
        WebhookEventListener::class => [
            ProductEvent::class,
            OrderEvent::class,
            AttendeeEvent::class,
            CheckinEvent::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        $this->registerDomainEventListeners();
    }

    /**
     * Dynamically register all domain event listeners.
     */
    private function registerDomainEventListeners(): void
    {
        foreach (self::$domainEventMap as $listener => $events) {
            foreach ($events as $event) {
                Event::listen($event, [$listener, 'handle']);
            }
        }
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }
}
