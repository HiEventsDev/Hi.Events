<?php

namespace HiEvents\Events;

use Illuminate\Foundation\Events\Dispatchable;
use HiEvents\DomainObjects\OrderDomainObject;

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
