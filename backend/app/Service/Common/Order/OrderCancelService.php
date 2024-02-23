<?php

namespace TicketKitten\Service\Common\Order;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Throwable;
use TicketKitten\DomainObjects\AttendeeDomainObject;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\DomainObjects\Status\AttendeeStatus;
use TicketKitten\DomainObjects\Status\OrderStatus;
use TicketKitten\Mail\OrderCancelled;
use TicketKitten\Repository\Interfaces\AttendeeRepositoryInterface;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Service\Common\Ticket\TicketQuantityService;

readonly class OrderCancelService
{
    public function __construct(
        private Mailer                      $mailer,
        private AttendeeRepositoryInterface $attendeeRepository,
        private EventRepositoryInterface    $eventRepository,
        private OrderRepositoryInterface    $orderRepository,
        private DatabaseManager             $databaseManager,
        private TicketQuantityService       $ticketQuantityService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function cancelOrder(OrderDomainObject $order): void
    {
        $this->databaseManager->transaction(function () use ($order) {
            $this->cancelAttendees($order);
            $this->adjustTicketQuantities($order);
            $this->updateOrderStatus($order);

            $event = $this->eventRepository->findById($order->getEventId());

            $this->mailer->to($order->getEmail())->send(new OrderCancelled($order, $event));
        });
    }

    private function cancelAttendees(OrderDomainObject $order): void
    {
        $this->attendeeRepository->updateWhere(
            attributes: [
                'status' => AttendeeStatus::CANCELLED->name,
            ],
            where: [
                'order_id' => $order->getId(),
            ]
        );
    }

    private function adjustTicketQuantities(OrderDomainObject $order): void
    {
        $attendees = $this->attendeeRepository->findWhere([
            'order_id' => $order->getId(),
        ]);

        $ticketIdCountMap = $attendees->map(fn(AttendeeDomainObject $attendee) => $attendee->getTicketPriceId())->countBy();

        foreach ($ticketIdCountMap as $ticketPriceId => $count) {
            $this->ticketQuantityService->decreaseTicketPriceQuantitySold($ticketPriceId, $count);
        }
    }

    private function updateOrderStatus(OrderDomainObject $order): void
    {
        $this->orderRepository->updateWhere(
            attributes: [
                'status' => OrderStatus::CANCELLED->name,
            ],
            where: [
                'id' => $order->getId(),
            ]
        );
    }
}
