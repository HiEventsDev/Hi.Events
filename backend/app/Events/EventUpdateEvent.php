<?php

namespace HiEvents\Events;

use HiEvents\DomainObjects\EventDomainObject;
use Illuminate\Foundation\Events\Dispatchable;

class EventUpdateEvent
{
    use Dispatchable;

    public function __construct(
        private readonly EventDomainObject $event,
    )
    {
    }
}
