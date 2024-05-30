<?php

namespace HiEvents\Events;

use Illuminate\Foundation\Events\Dispatchable;
use HiEvents\DomainObjects\EventDomainObject;

class EventUpdateEvent
{
    use Dispatchable;

    public function __construct(
        private readonly EventDomainObject $event,
    )
    {
    }
}
