<?php

namespace HiEvents\Events;

use HiEvents\DomainObjects\OrderDomainObject;
use Illuminate\Foundation\Events\Dispatchable;

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
