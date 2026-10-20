<?php

namespace HiEvents\Services\Application\Handlers\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimLiveness;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatingEventLockService;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\PartialEditAttendeeDTO;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCancellationService;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsReactivationService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class PartialEditAttendeeHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductQuantityUpdateService $productQuantityService,
        private readonly DatabaseManager $databaseManager,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly EventStatisticsCancellationService $eventStatisticsCancellationService,
        private readonly EventStatisticsReactivationService $eventStatisticsReactivationService,
        private readonly LoggerInterface $logger,
        private readonly SeatClaimService $seatClaimService,
        private readonly SeatedProductLookupService $seatedProductLookupService,
        private readonly SeatingEventLockService $seatingEventLock,
        private readonly OccurrencePurchaseEligibilityService $occurrenceEligibilityService,
        private readonly AvailableProductQuantitiesFetchService $availableProductQuantitiesFetchService,
    ) {}

    /**
     * @throws Throwable|ResourceNotFoundException
     */
    public function handle(PartialEditAttendeeDTO $data): AttendeeDomainObject
    {
        return $this->databaseManager->transaction(function () use ($data) {
            $status = $data->status ? strtoupper($data->status) : null;

            if ($status !== null) {
                $this->seatingEventLock->lock($data->event_id);
            }

            return $this->updateAttendee($data, $status);
        });
    }

    private function updateAttendee(PartialEditAttendeeDTO $data, ?string $status): AttendeeDomainObject
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            'id' => $data->attendee_id,
            'event_id' => $data->event_id,
        ]);

        if (! $attendee) {
            throw new ResourceNotFoundException;
        }

        $wasCancelled = $attendee->getStatus() === AttendeeStatus::CANCELLED->name;

        if ($status !== null && $wasCancelled && $status !== AttendeeStatus::CANCELLED->name) {
            $this->reactivate($status, $attendee);
        }

        if ($status === AttendeeStatus::CANCELLED->name && ! $wasCancelled) {
            $this->cancel($attendee);
        }

        return $this->attendeeRepository->updateByIdWhere(
            id: $data->attendee_id,
            attributes: [
                'status' => $status ?? $attendee->getStatus(),
                'first_name' => $data->first_name ?? $attendee->getFirstName(),
                'last_name' => $data->last_name ?? $attendee->getLastName(),
                'email' => $data->email ?? $attendee->getEmail(),
            ],
            where: [
                'event_id' => $data->event_id,
            ]);
    }

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    private function reactivate(string $status, AttendeeDomainObject $attendee): void
    {
        $seatedOrder = $this->assertSeatAllowsReactivation($status, $attendee);

        $this->assertRoomToReactivate($attendee);

        if ($seatedOrder !== null) {
            $this->reclaimSeat($attendee, $seatedOrder);
        }

        $this->productQuantityService->increaseQuantitySold($attendee->getProductPriceId(), 1, $attendee->getEventOccurrenceId());

        event(new CapacityChangedEvent(
            eventId: $attendee->getEventId(),
            direction: CapacityChangeDirection::DECREASED,
            productId: $attendee->getProductId(),
            productPriceId: $attendee->getProductPriceId(),
            eventOccurrenceId: $attendee->getEventOccurrenceId(),
        ));

        $order = $this->findOrderForStatistics($attendee);
        if ($order !== null) {
            $this->eventStatisticsReactivationService->incrementForReactivatedAttendee(
                eventId: $attendee->getEventId(),
                orderDate: $order->getCreatedAt(),
                occurrenceId: $attendee->getEventOccurrenceId(),
            );
        }
    }

    /**
     * @throws Throwable
     */
    private function cancel(AttendeeDomainObject $attendee): void
    {
        $this->seatClaimService->releaseForAttendee($attendee->getId());

        $this->productQuantityService->decreaseQuantitySold($attendee->getProductPriceId(), 1, $attendee->getEventOccurrenceId());

        event(new CapacityChangedEvent(
            eventId: $attendee->getEventId(),
            direction: CapacityChangeDirection::INCREASED,
            productId: $attendee->getProductId(),
            productPriceId: $attendee->getProductPriceId(),
            eventOccurrenceId: $attendee->getEventOccurrenceId(),
        ));

        $order = $this->findOrderForStatistics($attendee);
        if ($order !== null) {
            $this->eventStatisticsCancellationService->decrementForCancelledAttendee(
                eventId: $attendee->getEventId(),
                orderDate: $order->getCreatedAt(),
                occurrenceId: $attendee->getEventOccurrenceId(),
            );
        }

        $this->domainEventDispatcherService->dispatch(
            new AttendeeEvent(
                type: DomainEventType::ATTENDEE_CANCELLED,
                attendeeId: $attendee->getId(),
            )
        );
    }

    /**
     * @throws ValidationException
     */
    private function assertSeatAllowsReactivation(string $status, AttendeeDomainObject $attendee): ?OrderDomainObject
    {
        if ($attendee->getSeatUid() === null) {
            if ($this->seatedProductLookupService->isSeated($attendee->getProductId())) {
                throw ValidationException::withMessages([
                    'status' => __('This ticket now needs a seat, so this attendee cannot be reactivated. Add them again and choose a seat.'),
                ]);
            }

            return null;
        }

        if ($status !== AttendeeStatus::ACTIVE->name) {
            throw ValidationException::withMessages([
                'status' => __('A cancelled seated attendee can only be reactivated as active'),
            ]);
        }

        $order = $this->orderRepository->findById($attendee->getOrderId());
        if (! SeatClaimLiveness::orderKeepsSeatsIndefinitely($order->getStatus())) {
            throw ValidationException::withMessages([
                'status' => __('A seated attendee cannot be reactivated on an order that is not complete'),
            ]);
        }

        return $order;
    }

    /**
     * @throws ValidationException
     */
    private function assertRoomToReactivate(AttendeeDomainObject $attendee): void
    {
        if ($attendee->getEventOccurrenceId() !== null) {
            $this->assertDateHasRoom($attendee);
        }

        $availability = $this->availableProductQuantitiesFetchService->getAvailableProductQuantities(
            $attendee->getEventId(),
            ignoreCache: true,
            eventOccurrenceId: $attendee->getEventOccurrenceId(),
            applyOccurrenceLimits: false,
        );

        if ($availability->firstOverflowingPool([$attendee->getProductId() => 1]) !== null) {
            throw ValidationException::withMessages([
                'status' => __('The capacity shared by this ticket is full.'),
            ]);
        }

        $price = $availability->productQuantities->firstWhere('price_id', $attendee->getProductPriceId());
        $available = $attendee->getSeatUid() === null
            ? $price?->quantity_available
            : ($price?->quantity_available_before_seats ?? $price?->quantity_available);

        if (($available ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'status' => __('This ticket is sold out, so this attendee cannot be reactivated. Increase its quantity and try again.'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertDateHasRoom(AttendeeDomainObject $attendee): void
    {
        try {
            $this->occurrenceEligibilityService->assertOccurrencePurchasable(
                eventId: $attendee->getEventId(),
                occurrenceId: $attendee->getEventOccurrenceId(),
                allowPastOccurrence: true,
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'status' => collect($exception->errors())->flatten()->first(),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function reclaimSeat(AttendeeDomainObject $attendee, OrderDomainObject $order): void
    {
        try {
            $this->seatClaimService->reclaimForAttendee($attendee, $order);
        } catch (SeatsUnavailableException) {
            throw ValidationException::withMessages([
                'status' => __('Seat :seat has since been given to someone else', ['seat' => $attendee->getSeatLabel()]),
            ]);
        } catch (SeatSelectionInvalidException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }
    }

    private function findOrderForStatistics(AttendeeDomainObject $attendee): ?OrderDomainObject
    {
        $order = $this->orderRepository->findFirstWhere([
            'id' => $attendee->getOrderId(),
            'event_id' => $attendee->getEventId(),
        ]);

        if ($order === null) {
            $this->logger->error('Order not found when adjusting event statistics for attendee', [
                'attendee_id' => $attendee->getId(),
                'order_id' => $attendee->getOrderId(),
                'event_id' => $attendee->getEventId(),
            ]);
        }

        return $order;
    }
}
