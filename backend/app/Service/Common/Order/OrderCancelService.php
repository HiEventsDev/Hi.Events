<?php

namespace HiEvents\Service\Common\Order;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Throwable;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\OrderCancelled;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Service\Common\Ticket\TicketQuantityService;

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
