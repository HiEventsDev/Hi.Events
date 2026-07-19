<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\BulkOccurrenceAction;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderItemDomainObjectAbstract;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Exceptions\InvalidOccurrenceDatesException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\Occurrence\BulkCancelOccurrencesJob;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\BulkUpdateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\BulkUpdateOccurrencesResultDTO;
use HiEvents\Services\Domain\Event\RecurrenceRuleExclusionService;
use HiEvents\Services\Domain\EventLocation\EventLocationCleaner;
use HiEvents\Services\Domain\EventLocation\EventLocationUpserter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Throwable;

class BulkUpdateOccurrencesHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
        private readonly RecurrenceRuleExclusionService $exclusionService,
        private readonly EventLocationUpserter $eventLocationUpserter,
        private readonly EventLocationCleaner $eventLocationCleaner,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws InvalidOccurrenceDatesException
     * @throws Throwable
     */
    public function handle(BulkUpdateOccurrencesDTO $dto): BulkUpdateOccurrencesResultDTO
    {
        return $this->databaseManager->transaction(function () use ($dto) {
            $event = $this->eventRepository->findById($dto->event_id);
            if ($event === null) {
                throw new ResourceNotFoundException(__('Event :id not found', ['id' => $dto->event_id]));
            }

            $occurrences = $this->occurrenceRepository->findWhere(
                where: [
                    EventOccurrenceDomainObjectAbstract::EVENT_ID => $dto->event_id,
                ],
            );

            $eligible = $this->filterEligible($occurrences, $dto);

            return match ($dto->action) {
                BulkOccurrenceAction::CANCEL => $this->handleCancel($dto, $eligible),
                BulkOccurrenceAction::DELETE => $this->handleDelete($dto, $eligible),
                BulkOccurrenceAction::UPDATE => $this->handleUpdate($dto, $eligible, $event->getAccountId()),
            };
        });
    }

    private function filterEligible(Collection $occurrences, BulkUpdateOccurrencesDTO $dto): Collection
    {
        return $occurrences->filter(function (EventOccurrenceDomainObject $occurrence) use ($dto) {
            if (! empty($dto->occurrence_ids) && ! in_array($occurrence->getId(), $dto->occurrence_ids, true)) {
                return false;
            }

            if ($dto->action !== BulkOccurrenceAction::DELETE && $occurrence->getStatus() === EventOccurrenceStatus::CANCELLED->name) {
                return false;
            }

            if ($dto->future_only && $occurrence->isPast()) {
                return false;
            }

            if ($dto->skip_overridden && $occurrence->getIsOverridden()) {
                return false;
            }

            return true;
        });
    }

    private function handleCancel(BulkUpdateOccurrencesDTO $dto, Collection $eligible): BulkUpdateOccurrencesResultDTO
    {
        $ids = $this->collectIds($eligible);

        if (! empty($ids)) {
            BulkCancelOccurrencesJob::dispatch($dto->event_id, $ids, $dto->refund_orders);
        }

        return new BulkUpdateOccurrencesResultDTO(
            updated_count: count($ids),
            updated_ids: $ids,
        );
    }

    private function handleDelete(BulkUpdateOccurrencesDTO $dto, Collection $eligible): BulkUpdateOccurrencesResultDTO
    {
        $eligibleIds = $this->collectIds($eligible);

        if (empty($eligibleIds)) {
            return new BulkUpdateOccurrencesResultDTO(updated_count: 0, updated_ids: []);
        }

        $idsWithOrders = $this->orderItemRepository
            ->findWhereIn(
                field: OrderItemDomainObjectAbstract::EVENT_OCCURRENCE_ID,
                values: $eligibleIds,
                columns: [OrderItemDomainObjectAbstract::EVENT_OCCURRENCE_ID],
            )
            ->map(fn (OrderItemDomainObject $item) => $item->getEventOccurrenceId())
            ->flip()
            ->all();

        $idsWithAttendees = $this->attendeeRepository
            ->findWhereIn(
                field: AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID,
                values: $eligibleIds,
                columns: [AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID],
            )
            ->map(fn (AttendeeDomainObject $attendee) => $attendee->getEventOccurrenceId())
            ->flip()
            ->all();

        $deletableIds = [];
        $deletableStartDates = [];
        $deletableEventLocationIds = [];

        foreach ($eligible as $occurrence) {
            $id = $occurrence->getId();

            if (! isset($idsWithOrders[$id]) && ! isset($idsWithAttendees[$id])) {
                $deletableIds[] = $id;
                $deletableStartDates[] = $occurrence->getStartDate();
                if ($occurrence->getEventLocationId() !== null) {
                    $deletableEventLocationIds[] = $occurrence->getEventLocationId();
                }
            }
        }

        if (! empty($deletableIds)) {
            // FK is nullOnDelete; without this, WAITING/OFFERED entries scoped to
            // the deleted occurrences become orphans and crash ProcessWaitlistService
            // on the next CapacityChangedEvent.
            $this->waitlistEntryRepository->updateWhere(
                attributes: [
                    'status' => WaitlistEntryStatus::CANCELLED->name,
                ],
                where: [
                    'event_id' => $dto->event_id,
                    ['event_occurrence_id', 'in', $deletableIds],
                    ['status', 'in', [
                        WaitlistEntryStatus::WAITING->name,
                        WaitlistEntryStatus::OFFERED->name,
                    ]],
                ],
            );

            $this->occurrenceRepository->deleteWhere([
                [EventOccurrenceDomainObjectAbstract::ID, 'in', $deletableIds],
            ]);

            $this->exclusionService->addExclusions($dto->event_id, $deletableStartDates);

            foreach (array_unique($deletableEventLocationIds) as $eventLocationId) {
                $this->eventLocationCleaner->deleteIfOrphaned($eventLocationId);
            }
        }

        return new BulkUpdateOccurrencesResultDTO(
            updated_count: count($deletableIds),
            updated_ids: $deletableIds,
        );
    }

    private function handleUpdate(BulkUpdateOccurrencesDTO $dto, Collection $eligible, int $accountId): BulkUpdateOccurrencesResultDTO
    {
        $perRowEventLocation = $dto->event_location !== null || $dto->clear_event_location;

        $requiresPerRow = $dto->start_time_shift !== null
            || $dto->end_time_shift !== null
            || $dto->duration_minutes !== null
            || $perRowEventLocation;

        if ($requiresPerRow) {
            return $this->applyPerRowUpdate($dto, $eligible, $accountId);
        }

        return $this->applyUniformUpdate($dto, $eligible);
    }

    private function applyUniformUpdate(BulkUpdateOccurrencesDTO $dto, Collection $eligible): BulkUpdateOccurrencesResultDTO
    {
        $attributes = $this->buildUniformAttributes($dto);

        if (empty($attributes)) {
            return new BulkUpdateOccurrencesResultDTO(updated_count: 0, updated_ids: []);
        }

        $capacityChanged = array_key_exists(EventOccurrenceDomainObjectAbstract::CAPACITY, $attributes);

        if ($capacityChanged) {
            $attributes[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] = true;
        }

        $ids = $this->collectIds($eligible);

        if (empty($ids)) {
            return new BulkUpdateOccurrencesResultDTO(updated_count: 0, updated_ids: []);
        }

        $this->occurrenceRepository->updateWhere(
            attributes: $attributes,
            where: [
                [EventOccurrenceDomainObjectAbstract::ID, 'in', $ids],
            ],
        );

        return new BulkUpdateOccurrencesResultDTO(
            updated_count: count($ids),
            updated_ids: $ids,
        );
    }

    private function applyPerRowUpdate(BulkUpdateOccurrencesDTO $dto, Collection $eligible, int $accountId): BulkUpdateOccurrencesResultDTO
    {
        $updatedIds = [];
        $orphanCandidateIds = [];

        foreach ($eligible as $occurrence) {
            $attributes = $this->buildPerRowAttributes($dto, $occurrence);

            $previousEventLocationId = $occurrence->getEventLocationId();

            if ($dto->event_location !== null) {
                $eventLocation = $this->eventLocationUpserter->createForEvent(
                    eventId: $dto->event_id,
                    accountId: $accountId,
                    data: $dto->event_location,
                );
                $attributes[EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID] = $eventLocation->getId();
                $attributes[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] = true;

                if ($previousEventLocationId !== null) {
                    $orphanCandidateIds[] = $previousEventLocationId;
                }
            } elseif ($dto->clear_event_location && $previousEventLocationId !== null) {
                $attributes[EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID] = null;
                $attributes[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] = true;
                $orphanCandidateIds[] = $previousEventLocationId;
            }

            if (! empty($attributes)) {
                $this->occurrenceRepository->updateWhere(
                    attributes: $attributes,
                    where: [EventOccurrenceDomainObjectAbstract::ID => $occurrence->getId()],
                );
                $updatedIds[] = $occurrence->getId();
            }
        }

        foreach (array_unique($orphanCandidateIds) as $eventLocationId) {
            $this->eventLocationCleaner->deleteIfOrphaned($eventLocationId);
        }

        return new BulkUpdateOccurrencesResultDTO(
            updated_count: count($updatedIds),
            updated_ids: $updatedIds,
        );
    }

    private function buildUniformAttributes(BulkUpdateOccurrencesDTO $dto): array
    {
        $attributes = [];

        if ($dto->clear_capacity) {
            $attributes[EventOccurrenceDomainObjectAbstract::CAPACITY] = null;
        } elseif ($dto->capacity !== null) {
            $attributes[EventOccurrenceDomainObjectAbstract::CAPACITY] = $dto->capacity;
        }

        if ($dto->clear_label) {
            $attributes[EventOccurrenceDomainObjectAbstract::LABEL] = null;
        } elseif ($dto->label !== null) {
            $attributes[EventOccurrenceDomainObjectAbstract::LABEL] = $dto->label;
        }

        return $attributes;
    }

    private function buildPerRowAttributes(BulkUpdateOccurrencesDTO $dto, EventOccurrenceDomainObject $occurrence): array
    {
        $attributes = $this->buildUniformAttributes($dto);
        $startEndChanged = false;

        if ($dto->start_time_shift !== null && $dto->start_time_shift !== 0) {
            $start = Carbon::parse($occurrence->getStartDate(), 'UTC');
            $start->addMinutes($dto->start_time_shift);
            $attributes[EventOccurrenceDomainObjectAbstract::START_DATE] = $start->toDateTimeString();
            $startEndChanged = true;
        }

        if ($dto->end_time_shift !== null && $dto->end_time_shift !== 0 && $occurrence->getEndDate() !== null) {
            $end = Carbon::parse($occurrence->getEndDate(), 'UTC');
            $end->addMinutes($dto->end_time_shift);
            $attributes[EventOccurrenceDomainObjectAbstract::END_DATE] = $end->toDateTimeString();
            $startEndChanged = true;
        }

        if ($dto->duration_minutes !== null) {
            $startDate = $attributes[EventOccurrenceDomainObjectAbstract::START_DATE] ?? $occurrence->getStartDate();
            $start = Carbon::parse($startDate, 'UTC');
            $attributes[EventOccurrenceDomainObjectAbstract::END_DATE] = $start->copy()->addMinutes($dto->duration_minutes)->toDateTimeString();
            $startEndChanged = true;
        }

        if ($startEndChanged) {
            $this->guardResultingDates($attributes, $occurrence, $dto->timezone);
        }

        if ($startEndChanged
            || array_key_exists(EventOccurrenceDomainObjectAbstract::CAPACITY, $attributes)
        ) {
            $attributes[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] = true;
        }

        return $attributes;
    }

    /**
     * @throws InvalidOccurrenceDatesException
     */
    private function guardResultingDates(array $attributes, EventOccurrenceDomainObject $occurrence, string $timezone): void
    {
        $start = $attributes[EventOccurrenceDomainObjectAbstract::START_DATE] ?? $occurrence->getStartDate();
        $end = $attributes[EventOccurrenceDomainObjectAbstract::END_DATE] ?? $occurrence->getEndDate();

        if ($end !== null && Carbon::parse($end, 'UTC')->lessThanOrEqualTo(Carbon::parse($start, 'UTC'))) {
            throw new InvalidOccurrenceDatesException(
                __('This update would make the occurrence starting :start end before it starts. Adjust the time shift or duration.', [
                    'start' => Carbon::parse($occurrence->getStartDate(), 'UTC')->setTimezone($timezone)->format('M j, Y g:i A'),
                ]),
            );
        }
    }

    private function collectIds(Collection $eligible): array
    {
        return $eligible->map(fn (EventOccurrenceDomainObject $o) => $o->getId())->values()->all();
    }
}
