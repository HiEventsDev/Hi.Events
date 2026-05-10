<?php

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\Dispatcher;
use HiEvents\Events\EventUpdateEvent;
use HiEvents\Exceptions\CannotChangeCurrencyException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventDTO;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use HiEvents\Jobs\Event\Webhook\DispatchEventWebhookJob;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

readonly class UpdateEventHandler
{
    public function __construct(
        private EventRepositoryInterface             $eventRepository,
        private Dispatcher                           $dispatcher,
        private DatabaseManager                      $databaseManager,
        private OrderRepositoryInterface             $orderRepository,
        private HtmlPurifierService                  $purifier,
        private EventOccurrenceRepositoryInterface   $occurrenceRepository,
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
                'category' => $eventData->category?->value ?? $existingEvent->getCategory(),
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

        $this->updateSingleOccurrenceDates($eventData, $existingEvent);
    }

    private function updateSingleOccurrenceDates(UpdateEventDTO $eventData, EventDomainObject $existingEvent): void
    {
        if ($existingEvent->getType() !== EventType::SINGLE->name) {
            return;
        }

        if ($eventData->start_date === null) {
            return;
        }

        $timezone = $eventData->timezone ?? $existingEvent->getTimezone();

        $occurrence = $this->occurrenceRepository->findFirstWhere([
            'event_id' => $eventData->id,
        ]);

        if ($occurrence === null) {
            return;
        }

        $this->occurrenceRepository->updateWhere(
            attributes: [
                'start_date' => DateHelper::convertToUTC($eventData->start_date, $timezone),
                'end_date' => $eventData->end_date
                    ? DateHelper::convertToUTC($eventData->end_date, $timezone)
                    : null,
            ],
            where: [
                'id' => $occurrence->getId(),
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

        DispatchEventWebhookJob::dispatch(
            $event->getId(),
            DomainEventType::EVENT_UPDATED,
        );

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

