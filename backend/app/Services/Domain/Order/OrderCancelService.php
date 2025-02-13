<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\WebhookEventType;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Order\OrderCancelled;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\Webhook\WebhookDispatchService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Throwable;

class OrderCancelService
{
    public function __construct(
        private readonly Mailer                       $mailer,
        private readonly AttendeeRepositoryInterface  $attendeeRepository,
        private readonly EventRepositoryInterface     $eventRepository,
        private readonly OrderRepositoryInterface     $orderRepository,
        private readonly DatabaseManager              $databaseManager,
        private readonly ProductQuantityUpdateService $productQuantityService,
        private readonly WebhookDispatchService       $webhookDispatchService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function cancelOrder(OrderDomainObject $order): void
    {
        $this->databaseManager->transaction(function () use ($order) {
            $this->adjustProductQuantities($order);
            $this->cancelAttendees($order);
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

            $this->webhookDispatchService->queueOrderWebhook(
                eventType: WebhookEventType::ORDER_CANCELLED,
                orderId: $order->getId(),
            );
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
