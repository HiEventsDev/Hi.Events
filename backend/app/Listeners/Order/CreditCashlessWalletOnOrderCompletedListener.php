<?php

namespace HiEvents\Listeners\Order;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Jobs\Cashless\CreditCashlessWalletJob;

class CreditCashlessWalletOnOrderCompletedListener
{
    public function handle(OrderStatusChangedEvent $event): void
    {
        if ($event->order->getStatus() !== OrderStatus::COMPLETED->name) {
            return;
        }

        dispatch(new CreditCashlessWalletJob($event->order));
    }
}
