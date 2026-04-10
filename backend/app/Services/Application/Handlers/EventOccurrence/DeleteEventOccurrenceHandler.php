<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Throwable;

class DeleteEventOccurrenceHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly OrderItemRepositoryInterface       $orderItemRepository,
        private readonly AttendeeRepositoryInterface        $attendeeRepository,
        private readonly DatabaseManager                    $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(int $eventId, int $occurrenceId): void
    {
        $this->databaseManager->transaction(function () use ($eventId, $occurrenceId) {
            $occurrence = $this->occurrenceRepository->findFirstWhere([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ]);

            if (!$occurrence) {
                throw new ResourceNotFoundException(
                    __('Occurrence :id not found for event :eventId', [
                        'id' => $occurrenceId,
                        'eventId' => $eventId,
                    ])
                );
            }

            $orderCount = $this->orderItemRepository->countWhere([
                'event_occurrence_id' => $occurrenceId,
            ]);

            if ($orderCount > 0) {
                throw ValidationException::withMessages([
                    'occurrence' => __('Cannot delete an occurrence that has orders. Cancel it instead.'),
                ]);
            }

            $attendeeCount = $this->attendeeRepository->countWhere([
                'event_occurrence_id' => $occurrenceId,
            ]);

            if ($attendeeCount > 0) {
                throw ValidationException::withMessages([
                    'occurrence' => __('Cannot delete an occurrence that has attendees. Cancel it instead.'),
                ]);
            }

            $this->occurrenceRepository->deleteWhere([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
            ]);
        });
    }
}
