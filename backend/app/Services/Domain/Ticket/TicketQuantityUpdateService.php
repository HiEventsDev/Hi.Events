<?php

namespace HiEvents\Services\Domain\Ticket;

use InvalidArgumentException;
use HiEvents\DomainObjects\Generated\TicketPriceDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Repository\Interfaces\TicketPriceRepositoryInterface;

readonly class TicketQuantityUpdateService
{
    public function __construct(private TicketPriceRepositoryInterface $ticketPriceRepository)
    {
    }

    public function updateTicketQuantities(OrderDomainObject $order): void
    {
        if (!$order->getOrderItems() === null) {
            throw new InvalidArgumentException(__('Order has no order items'));
        }

        /** @var OrderItemDomainObject $orderItem */
        foreach ($order->getOrderItems() as $orderItem) {
            $this->ticketPriceRepository->increment(
                $orderItem->getTicketPriceId(),
                TicketPriceDomainObjectAbstract::QUANTITY_SOLD,
                $orderItem->getQuantity()
            );
        }
    }
}
