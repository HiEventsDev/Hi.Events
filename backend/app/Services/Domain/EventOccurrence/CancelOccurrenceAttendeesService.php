<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventOccurrence;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCancellationService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

class CancelOccurrenceAttendeesService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly ProductQuantityUpdateService $productQuantityService,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EventStatisticsCancellationService $statisticsCancellationService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array{attendee_ids: array<int, int>, sales_backed_count: int}
     */
    public function cancelForOccurrence(int $eventId, int $occurrenceId): array
    {
        $statusesToCancel = [AttendeeStatus::ACTIVE->name, AttendeeStatus::AWAITING_PAYMENT->name];

        $attendees = $this->attendeeRepository->findWhere([
            AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrenceId,
            [AttendeeDomainObjectAbstract::STATUS, 'in', $statusesToCancel],
        ]);

        if ($attendees->isEmpty()) {
            return ['attendee_ids' => [], 'sales_backed_count' => 0];
        }

        $this->attendeeRepository->updateWhere(
            attributes: [AttendeeDomainObjectAbstract::STATUS => AttendeeStatus::CANCELLED->name],
            where: [
                AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrenceId,
                [AttendeeDomainObjectAbstract::STATUS, 'in', $statusesToCancel],
            ],
        );

        $ordersById = $this->orderRepository
            ->findWhereIn('id', $attendees->map(fn (AttendeeDomainObject $attendee) => $attendee->getOrderId())->unique()->values()->all())
            ->keyBy(fn (OrderDomainObject $order) => $order->getId());

        $inventoryBackedAttendees = $attendees->filter(function (AttendeeDomainObject $attendee) use ($ordersById) {
            $order = $ordersById->get($attendee->getOrderId());

            return $order !== null && in_array($order->getStatus(), [
                OrderStatus::COMPLETED->name,
                OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
            ], true);
        });

        $soldCountsByProductPrice = $inventoryBackedAttendees
            ->map(fn (AttendeeDomainObject $attendee) => $attendee->getProductPriceId())
            ->countBy();

        foreach ($soldCountsByProductPrice as $productPriceId => $count) {
            $this->productQuantityService->decreaseQuantitySold(
                priceId: (int) $productPriceId,
                adjustment: $count,
                eventOccurrenceId: $occurrenceId,
            );
        }

        $statsBackedAttendees = $attendees->filter(function (AttendeeDomainObject $attendee) use ($ordersById) {
            $order = $ordersById->get($attendee->getOrderId());

            return $order !== null && $order->getStatus() === OrderStatus::COMPLETED->name;
        });

        $this->decrementStatisticsForCancelledAttendees($eventId, $occurrenceId, $statsBackedAttendees, $ordersById);

        foreach ($attendees as $attendee) {
            $this->domainEventDispatcherService->dispatch(new AttendeeEvent(
                type: DomainEventType::ATTENDEE_CANCELLED,
                attendeeId: $attendee->getId(),
            ));
        }

        $productIds = $inventoryBackedAttendees
            ->map(fn (AttendeeDomainObject $attendee) => $attendee->getProductId())
            ->unique()
            ->values()
            ->all();

        foreach ($productIds as $productId) {
            event(new CapacityChangedEvent(
                eventId: $eventId,
                direction: CapacityChangeDirection::INCREASED,
                productId: $productId,
                eventOccurrenceId: $occurrenceId,
            ));
        }

        return [
            'attendee_ids' => $attendees->map(fn (AttendeeDomainObject $attendee) => $attendee->getId())->values()->all(),
            'sales_backed_count' => $inventoryBackedAttendees->count(),
        ];
    }

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     * @param  Collection<int, OrderDomainObject>  $ordersById
     */
    private function decrementStatisticsForCancelledAttendees(
        int $eventId,
        int $occurrenceId,
        Collection $attendees,
        Collection $ordersById,
    ): void {
        $countsByOrderId = $attendees
            ->groupBy(fn (AttendeeDomainObject $attendee) => $attendee->getOrderId())
            ->map->count();

        foreach ($countsByOrderId as $orderId => $attendeeCount) {
            $order = $ordersById->get((int) $orderId);
            if ($order === null) {
                continue;
            }

            try {
                $this->statisticsCancellationService->decrementForCancelledAttendee(
                    eventId: $eventId,
                    orderDate: $order->getCreatedAt(),
                    attendeeCount: $attendeeCount,
                    occurrenceId: $occurrenceId,
                );
            } catch (Throwable $e) {
                $this->logger->error(
                    'Failed to decrement attendee statistics during occurrence cancellation',
                    [
                        'event_id' => $eventId,
                        'occurrence_id' => $occurrenceId,
                        'order_id' => $orderId,
                        'attendee_count' => $attendeeCount,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ],
                );
            }
        }
    }
}
