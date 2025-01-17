<?php

namespace HiEvents\Listeners\Order;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Invoice\InvoiceCreateService;

class CreateInvoiceListener
{
    public function __construct(private readonly InvoiceCreateService $invoiceCreateService)
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(OrderStatusChangedEvent $event): void
    {
        if (!$event->createInvoice) {
            return;
        }

        $order = $event->order;

        if ($order->getStatus() !== OrderStatus::AWAITING_OFFLINE_PAYMENT->name && $order->getStatus() !== OrderStatus::COMPLETED->name) {
            return;
        }

        $this->invoiceCreateService->createInvoiceForOrder($order->getId());
    }
}
