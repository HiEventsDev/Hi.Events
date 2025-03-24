<?php

namespace HiEvents\Services\Infrastructure\DomainEvents;

use HiEvents\Services\Infrastructure\DomainEvents\Events\BaseDomainEvent;
use Illuminate\Events\Dispatcher as EventDispatcher;


class DomainEventDispatcherService
{
    public function __construct(private readonly EventDispatcher $dispatcher)
    {
    }

    public function dispatch(BaseDomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
