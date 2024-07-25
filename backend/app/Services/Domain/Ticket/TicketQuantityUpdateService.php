<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\DomainObjects\Generated\CapacityAssignmentDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketPriceDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use InvalidArgumentException;

class TicketQuantityUpdateService
{
    public function __construct(
        private readonly TicketPriceRepositoryInterface        $ticketPriceRepository,
        private readonly TicketRepositoryInterface             $ticketRepository,
        private readonly CapacityAssignmentRepositoryInterface $capacityAssignmentRepository,
    )
    {
    }

    public function updateQuantities(OrderDomainObject $order): void
    {
        if (!$order->getOrderItems() === null) {
            throw new InvalidArgumentException(__('Order has no order items'));
        }

        $this->updateTicketQuantities($order);
        $this->updateCapacityAssignments($order);

    }

    /**
     * @param OrderDomainObject $order
     * @return void
     */
    private function updateCapacityAssignments(OrderDomainObject $order): void
    {
        /** @var OrderItemDomainObject $orderItem */
        foreach ($order->getOrderItems() as $orderItem) {
            $ticketPrice = $this->ticketPriceRepository->findById($orderItem->getTicketPriceId());
            $capacityAssignments = $this->ticketRepository->getCapacityAssignmentsByTicketId($ticketPrice->getTicketId());

            foreach ($capacityAssignments as $capacityAssignment) {
                $this->capacityAssignmentRepository->increment(
                    id: $capacityAssignment->getId(),
                    column: CapacityAssignmentDomainObjectAbstract::USED_CAPACITY,
                    amount: $orderItem->getQuantity()
                );
            }
        }
    }

    /**
     * @param OrderDomainObject $order
     * @return void
     */
    private function updateTicketQuantities(OrderDomainObject $order): void
    {
        /** @var OrderItemDomainObject $orderItem */
        foreach ($order->getOrderItems() as $orderItem) {
            $this->ticketPriceRepository->increment(
                id: $orderItem->getTicketPriceId(),
                column: TicketPriceDomainObjectAbstract::QUANTITY_SOLD,
                amount: $orderItem->getQuantity()
            );
        }
    }
}
