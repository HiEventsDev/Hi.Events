<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Order\OrderCancelled;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Throwable;

readonly class OrderCancelService
{
    public function __construct(
        private Mailer                       $mailer,
        private AttendeeRepositoryInterface  $attendeeRepository,
        private EventRepositoryInterface     $eventRepository,
        private OrderRepositoryInterface     $orderRepository,
        private DatabaseManager              $databaseManager,
        private ProductQuantityUpdateService $productQuantityService,
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
            $this->adjustProductQuantities($order);
            $this->updateOrderStatus($order);

            $event = $this->eventRepository
                ->loadRelation(EventSettingDomainObject::class)
                ->findById($order->getEventId());

            $this->mailer
                ->to($order->getEmail())
                ->locale($order->getLocale())
                ->send(new OrderCancelled(
                    order: $order,
                    event: $event,
                    eventSettings: $event->getEventSettings(),
                ));
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

    private function adjustProductQuantities(OrderDomainObject $order): void
    {
        $attendees = $this->attendeeRepository->findWhere([
            'order_id' => $order->getId(),
            'status' => AttendeeStatus::ACTIVE->name,
        ]);

        $productIdCountMap = $attendees
            ->map(fn(AttendeeDomainObject $attendee) => $attendee->getProductPriceId())->countBy();

        foreach ($productIdCountMap as $productPriceId => $count) {
            $this->productQuantityService->decreaseQuantitySold($productPriceId, $count);
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
