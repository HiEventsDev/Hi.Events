<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\Occurrence\SendOccurrenceCancellationEmailJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Domain\Event\RecurrenceRuleExclusionService;
use HiEvents\Services\Domain\EventOccurrence\CancelOccurrenceAttendeesService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OccurrenceEvent;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CancelOccurrenceHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly RecurrenceRuleExclusionService $exclusionService,
        private readonly CancelOccurrenceAttendeesService $cancelAttendeesService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(int $eventId, int $occurrenceId, bool $refundOrders = false): EventOccurrenceDomainObject
    {
        $wasCancelled = false;
        $cancelledAttendeeIds = [];

        $updated = $this->databaseManager->transaction(function () use ($eventId, $occurrenceId, &$wasCancelled, &$cancelledAttendeeIds) {
            $occurrence = $this->occurrenceRepository->findByIdLocked($occurrenceId);

            if (! $occurrence || $occurrence->getEventId() !== $eventId) {
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

            $cancelResult = $this->cancelAttendeesService->cancelForOccurrence($eventId, $occurrenceId);
            $cancelledAttendeeIds = $cancelResult['attendee_ids'];

            $updated = $this->occurrenceRepository->updateFromArray(
                id: $occurrenceId,
                attributes: [
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::CANCELLED->name,
                    EventOccurrenceDomainObjectAbstract::CANCELLED_ATTENDEES_COUNT => $cancelResult['sales_backed_count'],
                ],
            );

            $this->exclusionService->addExclusions($eventId, [$occurrence->getStartDate()]);

            $wasCancelled = true;

            return $updated;
        });

        if ($wasCancelled) {
            SendOccurrenceCancellationEmailJob::dispatchChunked($eventId, $occurrenceId, $cancelledAttendeeIds, $refundOrders);

            event(new OccurrenceCancelledEvent(
                eventId: $eventId,
                occurrenceId: $occurrenceId,
                refundOrders: $refundOrders,
            ));

            event(new OccurrenceEvent(
                type: DomainEventType::OCCURRENCE_CANCELLED,
                occurrenceId: $occurrenceId,
            ));
        }

        return $updated;
    }
}
