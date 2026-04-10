<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\Occurrence\RefundOccurrenceOrdersJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CancelOccurrenceHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly EventRepositoryInterface           $eventRepository,
        private readonly DatabaseManager                    $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(int $eventId, int $occurrenceId, bool $refundOrders = false): EventOccurrenceDomainObject
    {
        $wasCancelled = false;

        $updated = $this->databaseManager->transaction(function () use ($eventId, $occurrenceId, &$wasCancelled) {
            // Lock the row for the duration of this transaction so a concurrent cancel
            // (single or bulk) cannot pass the same status check and double-dispatch
            // the refund / notification side-effects below.
            $occurrence = $this->occurrenceRepository->findByIdLocked($occurrenceId);

            if (!$occurrence || $occurrence->getEventId() !== $eventId) {
                throw new ResourceNotFoundException(
                    __('Occurrence :id not found for event :eventId', [
                        'id' => $occurrenceId,
                        'eventId' => $eventId,
                    ])
                );
            }

            if ($occurrence->getStatus() === EventOccurrenceStatus::CANCELLED->name) {
                return $occurrence;
            }

            $updated = $this->occurrenceRepository->updateFromArray(
                id: $occurrenceId,
                attributes: [
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::CANCELLED->name,
                ],
            );

            $event = $this->eventRepository->findByIdLocked($eventId);

            if ($event->getType() === EventType::RECURRING->name) {
                $recurrenceRule = $event->getRecurrenceRule() ?? [];
                if (is_string($recurrenceRule)) {
                    $recurrenceRule = json_decode($recurrenceRule, true, 512, JSON_THROW_ON_ERROR);
                }
                $excludedDates = $recurrenceRule['excluded_dates'] ?? [];

                $startDate = date('Y-m-d', strtotime($occurrence->getStartDate()));
                if (!in_array($startDate, $excludedDates, true)) {
                    $excludedDates[] = $startDate;
                    $recurrenceRule['excluded_dates'] = $excludedDates;

                    $this->eventRepository->updateFromArray(
                        id: $eventId,
                        attributes: [
                            EventDomainObjectAbstract::RECURRENCE_RULE => $recurrenceRule,
                        ],
                    );
                }
            }

            $wasCancelled = true;

            return $updated;
        });

        if ($wasCancelled) {
            event(new OccurrenceCancelledEvent(
                eventId: $eventId,
                occurrenceId: $occurrenceId,
                refundOrders: $refundOrders,
            ));

            if ($refundOrders) {
                RefundOccurrenceOrdersJob::dispatch($eventId, $occurrenceId);
            }
        }

        return $updated;
    }
}
