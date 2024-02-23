<?php

namespace TicketKitten\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TicketKitten\DomainObjects\EventDomainObject;

class EventUpdateEvent
{
    use Dispatchable;

    public function __construct(
        private EventDomainObject $event,
    )
    {
    }
}
