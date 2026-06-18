<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Mail\Order\OrderCancelled;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCancellationService;
use HiEvents\Services\Domain\Mail\MailBrandingService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Throwable;

class OrderCancelService
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly ProductQuantityUpdateService $productQuantityService,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly EventStatisticsCancellationService $eventStatisticsCancellationService,
        private readonly MailBrandingService $mailBrandingService,
    ) {}

    /**
     * @throws Throwable
     */
    public function cancelOrder(OrderDomainObject $order): void
    {
        $this->databaseManager->transaction(function () use ($order) {
            // Order of operations matters here. We must decrement the stats first.
            $this->eventStatisticsCancellationService->decrementForCancelledOrder($order);

            $this->adjustProductQuantities($order);
            $this->cancelAttendees($order);
            $this->updateOrderStatus($order);

            $event = $this->eventRepository
                ->loadRelation(new Relationship(OrganizerDomainObject::class, nested: [
                    new Relationship(domainObject: OrganizerSettingDomainObject::class, name: 'organizer_settings'),
                    new Relationship(domainObject: ImageDomainObject::class),
                ], name: 'organizer'))
                ->loadRelation(ProductDomainObject::class)
                ->loadRelation(EventSettingDomainObject::class)
                ->findById($order->getEventId());

            $mail = new OrderCancelled(
                order: $order,
                event: $event,
                organizer: $event->getOrganizer(),
                eventSettings: $event->getEventSettings(),
            );

            $this->mailer
                ->to($order->getEmail())
                ->locale($order->getLocale())
                ->send($mail->withBranding($this->mailBrandingService->fromEvent($event)));

            $this->domainEventDispatcherService->dispatch(
                new OrderEvent(
                    type: DomainEventType::ORDER_CANCELLED,
                    orderId: $order->getId(),
                ),
            );

            $this->dispatchCapacityChangedEvents($order);
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
        ])->filter(function (AttendeeDomainObject $attendee) use ($order) {
            if ($order->isOrderAwaitingOfflinePayment()) {
                return $attendee->getStatus() === AttendeeStatus::ACTIVE->name
                    || $attendee->getStatus() === AttendeeStatus::AWAITING_PAYMENT->name;
            }

            return $attendee->getStatus() === AttendeeStatus::ACTIVE->name;
        });

        $groupedCounts = $attendees
            ->map(fn (AttendeeDomainObject $attendee) => $attendee->getProductPriceId().'_'.$attendee->getEventOccurrenceId())
            ->countBy();

        foreach ($groupedCounts as $compositeKey => $count) {
            [$productPriceId, $eventOccurrenceId] = explode('_', (string) $compositeKey);
            $this->productQuantityService->decreaseQuantitySold(
                (int) $productPriceId,
                $count,
                $eventOccurrenceId ? (int) $eventOccurrenceId : null,
            );
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

    private function dispatchCapacityChangedEvents(OrderDomainObject $order): void
    {
        $attendees = $this->attendeeRepository->findWhere([
            'order_id' => $order->getId(),
        ]);

        $capacityScopes = $attendees
            ->map(fn (AttendeeDomainObject $attendee) => [
                'product_id' => $attendee->getProductId(),
                'event_occurrence_id' => $attendee->getEventOccurrenceId(),
            ])
            ->unique(fn (array $scope) => $scope['product_id'].'-'.$scope['event_occurrence_id']);

        foreach ($capacityScopes as $scope) {
            event(new CapacityChangedEvent(
                eventId: $order->getEventId(),
                direction: CapacityChangeDirection::INCREASED,
                productId: $scope['product_id'],
                eventOccurrenceId: $scope['event_occurrence_id'],
            ));
        }
    }
}
