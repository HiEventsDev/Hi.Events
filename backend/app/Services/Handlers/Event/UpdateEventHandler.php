<?php

namespace HiEvents\Services\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\Dispatcher;
use HiEvents\Events\EventUpdateEvent;
use HiEvents\Exceptions\CannotChangeCurrencyException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Handlers\Event\DTO\UpdateEventDTO;
use HTMLPurifier;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

readonly class UpdateEventHandler
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private Dispatcher               $dispatcher,
        private DatabaseManager          $databaseManager,
        private OrderRepositoryInterface $orderRepository,
        private HTMLPurifier             $purifier,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpdateEventDTO $eventData): EventDomainObject
    {
        return $this->databaseManager->transaction(function () use ($eventData) {
            $this->updateEventAttributes($eventData);

            return $this->getUpdateEvent($eventData);
        });
    }

    private function fetchExistingEvent(UpdateEventDTO $eventData)
    {
        $existingEvent = $this->eventRepository->findFirstWhere([
            'id' => $eventData->id,
            'account_id' => $eventData->account_id,
        ]);

        if ($existingEvent === null) {
            throw new ResourceNotFoundException(
                __('Event :id not found', ['id' => $eventData->id])
            );
        }

        return $existingEvent;
    }

    /**
     * @throws CannotChangeCurrencyException
     */
    private function updateEventAttributes(UpdateEventDTO $eventData): void
    {
        $existingEvent = $this->fetchExistingEvent($eventData);

        if ($eventData->currency !== null && $eventData->currency !== $existingEvent->getCurrency()) {
            $this->checkForCompletedOrders($eventData);
        }

        $this->eventRepository->updateWhere(
            attributes: [
                'title' => $eventData->title,
                'start_date' => DateHelper::convertToUTC($eventData->start_date, $eventData->timezone),
                'end_date' => $eventData->end_date
                    ? DateHelper::convertToUTC($eventData->end_date, $eventData->timezone)
                    : null,
                'description' => $this->purifier->purify($eventData->description),
                'timezone' => $eventData->timezone ?? $existingEvent->getTimezone(),
                'currency' => $eventData->currency ?? $existingEvent->getCurrency(),
                'location' => $eventData->location,
                'location_details' => $eventData->location_details?->toArray(),
            ],
            where: [
                'id' => $eventData->id,
                'account_id' => $eventData->account_id,
            ],
        );
    }

    private function getUpdateEvent(UpdateEventDTO $eventData): EventDomainObject
    {
        $event = $this->eventRepository->findFirstWhere([
            'id' => $eventData->id,
            'account_id' => $eventData->account_id,
        ]);

        $this->dispatcher->dispatchEvent(new EventUpdateEvent($event));

        return $event;
    }

    /**
     * @throws CannotChangeCurrencyException
     */
    private function checkForCompletedOrders(UpdateEventDTO $eventData): void
    {
        $orders = $this->orderRepository->findWhere([
            'event_id' => $eventData->id,
            'status' => OrderStatus::COMPLETED->name,
        ]);

        if (!$orders->isNotEmpty()) {
            throw new CannotChangeCurrencyException(
                __('You cannot change the currency of an event that has completed orders'),
            );
        }
    }
}

