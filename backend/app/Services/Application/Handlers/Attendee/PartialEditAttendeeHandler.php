<?php

namespace HiEvents\Services\Application\Handlers\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\PartialEditAttendeeDTO;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCancellationService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class PartialEditAttendeeHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface       $attendeeRepository,
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly ProductQuantityUpdateService      $productQuantityService,
        private readonly DatabaseManager                   $databaseManager,
        private readonly DomainEventDispatcherService      $domainEventDispatcherService,
        private readonly EventStatisticsCancellationService $eventStatisticsCancellationService,
    )
    {
    }

    /**
     * @throws Throwable|ResourceNotFoundException
     */
    public function handle(PartialEditAttendeeDTO $data): AttendeeDomainObject
    {
        return $this->databaseManager->transaction(function () use ($data) {
            return $this->updateAttendee($data);
        });
    }

    private function updateAttendee(PartialEditAttendeeDTO $data): AttendeeDomainObject
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            'id' => $data->attendee_id,
            'event_id' => $data->event_id,
        ]);

        if (!$attendee) {
            throw new ResourceNotFoundException();
        }

        $statusIsUpdated = $data->status && $data->status !== $attendee->getStatus();

        if ($statusIsUpdated) {
            $this->adjustProductQuantity($data, $attendee);
            $this->adjustEventStatistics($data, $attendee);
        }

        if ($statusIsUpdated && $data->status === AttendeeStatus::CANCELLED->name) {
            $this->domainEventDispatcherService->dispatch(
                new AttendeeEvent(
                    type: DomainEventType::ATTENDEE_CANCELLED,
                    attendeeId: $attendee->getId(),
                )
            );
        }

        return $this->attendeeRepository->updateByIdWhere(
            id: $data->attendee_id,
            attributes: [
                'status' => $data->status
                    ? strtoupper($data->status)
                    : $attendee->getStatus(),
                'first_name' => $data->first_name ?? $attendee->getFirstName(),
                'last_name' => $data->last_name ?? $attendee->getLastName(),
                'email' => $data->email ?? $attendee->getEmail(),
            ],
            where: [
                'event_id' => $data->event_id,
            ]);
    }

    /**
     * @todo - we should check product availability before updating the product quantity
     */
    private function adjustProductQuantity(PartialEditAttendeeDTO $data, AttendeeDomainObject $attendee): void
    {
        if ($data->status === AttendeeStatus::ACTIVE->name) {
            $this->productQuantityService->increaseQuantitySold($attendee->getProductPriceId());
        } elseif ($data->status === AttendeeStatus::CANCELLED->name) {
            $this->productQuantityService->decreaseQuantitySold($attendee->getProductPriceId());
        }
    }

    /**
     * Adjust event statistics when attendee status changes
     *
     * @throws Throwable
     */
    private function adjustEventStatistics(PartialEditAttendeeDTO $data, AttendeeDomainObject $attendee): void
    {
        if ($data->status === AttendeeStatus::CANCELLED->name) {
            // Get the order to access the creation date for daily statistics
            $order = $this->orderRepository->findById($attendee->getOrderId());

            $this->eventStatisticsCancellationService->decrementForCancelledAttendee(
                eventId: $attendee->getEventId(),
                orderDate: $order->getCreatedAt()
            );
        }
    }
}
