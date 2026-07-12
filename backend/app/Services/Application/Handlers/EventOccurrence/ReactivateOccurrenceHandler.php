<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Domain\Event\RecurrenceRuleExclusionService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReactivateOccurrenceHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly RecurrenceRuleExclusionService $exclusionService,
        private readonly DatabaseManager $databaseManager,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(int $eventId, int $occurrenceId): EventOccurrenceDomainObject
    {
        return $this->databaseManager->transaction(function () use ($eventId, $occurrenceId) {
            $occurrence = $this->occurrenceRepository->findByIdLocked($occurrenceId);

            if (! $occurrence || $occurrence->getEventId() !== $eventId) {
                throw new ResourceNotFoundException(
                    __('Occurrence :id not found for event :eventId', [
                        'id' => $occurrenceId,
                        'eventId' => $eventId,
                    ])
                );
            }

            if ($occurrence->getStatus() !== EventOccurrenceStatus::CANCELLED->name) {
                throw ValidationException::withMessages([
                    'status' => __('Only cancelled dates can be reactivated.'),
                ]);
            }

            $cancelledAttendeeCount = $this->attendeeRepository->countWhere([
                AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrenceId,
                AttendeeDomainObjectAbstract::STATUS => AttendeeStatus::CANCELLED->name,
            ]);

            if ($cancelledAttendeeCount > 0) {
                throw ValidationException::withMessages([
                    'status' => __('This date had ticket sales that were cancelled with it, so it can\'t be reactivated automatically. Please create a new date instead.'),
                ]);
            }

            $updated = $this->occurrenceRepository->updateFromArray(
                id: $occurrenceId,
                attributes: [
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::ACTIVE->name,
                ],
            );

            $this->exclusionService->removeExclusion($eventId, $occurrence->getStartDate());

            return $updated;
        });
    }
}
