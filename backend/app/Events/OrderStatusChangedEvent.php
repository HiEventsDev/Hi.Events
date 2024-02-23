<?php

namespace TicketKitten\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TicketKitten\DomainObjects\OrderDomainObject;

class OrderStatusChangedEvent
{
    use Dispatchable;

    public function __construct(
        public OrderDomainObject $order,
        public bool              $sendEmails = true,
    )
    {
    }
}
