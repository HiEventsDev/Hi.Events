<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeleteEventOccurrenceHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

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

            if (! $occurrence) {
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

            $occurrenceStartDate = $occurrence->getStartDate();

            $this->waitlistEntryRepository->updateWhere(
                attributes: [
                    'status' => WaitlistEntryStatus::CANCELLED->name,
                ],
                where: [
                    'event_id' => $eventId,
                    'event_occurrence_id' => $occurrenceId,
                    ['status', 'in', [
                        WaitlistEntryStatus::WAITING->name,
                        WaitlistEntryStatus::OFFERED->name,
                    ]],
                ],
            );

            $this->occurrenceRepository->deleteWhere([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
            ]);

            $this->appendOccurrenceToRecurrenceExclusions($eventId, $occurrenceStartDate);
        });
    }

    private function appendOccurrenceToRecurrenceExclusions(int $eventId, string $startDate): void
    {
        $event = $this->eventRepository->findByIdLocked($eventId);

        if ($event === null || $event->getType() !== EventType::RECURRING->name) {
            return;
        }

        $recurrenceRule = $event->getRecurrenceRule() ?? [];
        if (is_string($recurrenceRule)) {
            $recurrenceRule = json_decode($recurrenceRule, true, 512, JSON_THROW_ON_ERROR);
        }

        $excludedOccurrences = $recurrenceRule['excluded_occurrences'] ?? [];
        $startDateTime = CarbonImmutable::parse($startDate, 'UTC')
            ->setTimezone($event->getTimezone() ?? 'UTC')
            ->format('Y-m-d H:i');

        if (in_array($startDateTime, $excludedOccurrences, true)) {
            return;
        }

        $excludedOccurrences[] = $startDateTime;
        $recurrenceRule['excluded_occurrences'] = $excludedOccurrences;

        $this->eventRepository->updateFromArray(
            id: $eventId,
            attributes: [
                EventDomainObjectAbstract::RECURRENCE_RULE => $recurrenceRule,
            ],
        );
    }
}
