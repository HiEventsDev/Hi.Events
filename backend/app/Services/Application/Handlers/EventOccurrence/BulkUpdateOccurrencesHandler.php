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
use HiEvents\Jobs\Occurrence\BulkCancelOccurrencesJob;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\BulkUpdateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\BulkUpdateOccurrencesResultDTO;
use HiEvents\Services\Domain\Event\RecurrenceRuleExclusionService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Throwable;

class BulkUpdateOccurrencesHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
        private readonly RecurrenceRuleExclusionService $exclusionService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(BulkUpdateOccurrencesDTO $dto): BulkUpdateOccurrencesResultDTO
    {
        return $this->databaseManager->transaction(function () use ($dto) {
            $occurrences = $this->occurrenceRepository->findWhere(
                where: [
                    EventOccurrenceDomainObjectAbstract::EVENT_ID => $dto->event_id,
                ],
            );

            $eligible = $this->filterEligible($occurrences, $dto);

            return match ($dto->action) {
                BulkOccurrenceAction::CANCEL => $this->handleCancel($dto, $eligible),
                BulkOccurrenceAction::DELETE => $this->handleDelete($dto, $eligible),
                BulkOccurrenceAction::UPDATE => $this->handleUpdate($dto, $eligible),
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

        // Two batched lookups instead of 2N countWhere calls — bulk delete
        // during regenerate can touch hundreds of occurrences. Mirrors the
        // single-delete handler: attendees can exist without order items
        // (imports, legacy data), so both checks must run.
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

        foreach ($eligible as $occurrence) {
            $id = $occurrence->getId();

            if (! isset($idsWithOrders[$id]) && ! isset($idsWithAttendees[$id])) {
                $deletableIds[] = $id;
                $deletableStartDates[] = $occurrence->getStartDate();
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
        }

        return new BulkUpdateOccurrencesResultDTO(
            updated_count: count($deletableIds),
            updated_ids: $deletableIds,
        );
    }

    private function handleUpdate(BulkUpdateOccurrencesDTO $dto, Collection $eligible): BulkUpdateOccurrencesResultDTO
    {
        $requiresPerRow = $dto->start_time_shift !== null
            || $dto->end_time_shift !== null
            || $dto->duration_minutes !== null;

        if ($requiresPerRow) {
            return $this->applyPerRowUpdate($dto, $eligible);
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

        // Capacity changes diverge the occurrence from the rule's defaults —
        // flag overridden so the next regenerate doesn't reset them. Label-only
        // edits don't pin against regenerate (parity with single-edit handler).
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

        // Mirror the single-edit handler's status reconciliation: capacity is
        // uniform across the batch but each occurrence carries its own
        // used_capacity, so SOLD_OUT/ACTIVE has to flip per row. Two scoped
        // updateWheres avoid an N-row loop while still respecting the uniform
        // path. CANCELLED is left untouched on either side — its lifecycle
        // belongs to the dedicated cancel/reactivate handlers.
        if ($capacityChanged) {
            $this->reconcileStatusForUniformCapacity(
                ids: $ids,
                newCapacity: $attributes[EventOccurrenceDomainObjectAbstract::CAPACITY],
            );
        }

        return new BulkUpdateOccurrencesResultDTO(
            updated_count: count($ids),
            updated_ids: $ids,
        );
    }

    /**
     * @param  int[]  $ids
     */
    private function reconcileStatusForUniformCapacity(array $ids, ?int $newCapacity): void
    {
        // Re-open any sold-out occurrence that now has headroom (or is now
        // unlimited).
        $reopenWhere = [
            [EventOccurrenceDomainObjectAbstract::ID, 'in', $ids],
            [EventOccurrenceDomainObjectAbstract::STATUS, '=', EventOccurrenceStatus::SOLD_OUT->name],
        ];
        if ($newCapacity !== null) {
            $reopenWhere[] = [EventOccurrenceDomainObjectAbstract::USED_CAPACITY, '<', $newCapacity];
        }

        $this->occurrenceRepository->updateWhere(
            attributes: [
                EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::ACTIVE->name,
            ],
            where: $reopenWhere,
        );

        // Mark sold-out any active occurrence that the new ceiling has now
        // exceeded. Only meaningful when the new capacity is finite.
        if ($newCapacity !== null) {
            $this->occurrenceRepository->updateWhere(
                attributes: [
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::SOLD_OUT->name,
                ],
                where: [
                    [EventOccurrenceDomainObjectAbstract::ID, 'in', $ids],
                    [EventOccurrenceDomainObjectAbstract::STATUS, '=', EventOccurrenceStatus::ACTIVE->name],
                    [EventOccurrenceDomainObjectAbstract::USED_CAPACITY, '>=', $newCapacity],
                ],
            );
        }
    }

    private function applyPerRowUpdate(BulkUpdateOccurrencesDTO $dto, Collection $eligible): BulkUpdateOccurrencesResultDTO
    {
        $updatedIds = [];

        foreach ($eligible as $occurrence) {
            $attributes = $this->buildPerRowAttributes($dto, $occurrence);

            if (! empty($attributes)) {
                // Same SOLD_OUT/ACTIVE reconciliation as the single-edit and
                // uniform-bulk paths — when the bulk operation also tweaks
                // capacity (e.g. shift_times + clear_capacity in one save) the
                // status has to follow the new ceiling per row.
                if (array_key_exists(EventOccurrenceDomainObjectAbstract::CAPACITY, $attributes)) {
                    $reconciled = $this->reconcileCapacityStatus(
                        currentStatus: $occurrence->getStatus(),
                        newCapacity: $attributes[EventOccurrenceDomainObjectAbstract::CAPACITY],
                        usedCapacity: $occurrence->getUsedCapacity() ?? 0,
                    );
                    if ($reconciled !== null) {
                        $attributes[EventOccurrenceDomainObjectAbstract::STATUS] = $reconciled;
                    }
                }

                $this->occurrenceRepository->updateWhere(
                    attributes: $attributes,
                    where: [EventOccurrenceDomainObjectAbstract::ID => $occurrence->getId()],
                );
                $updatedIds[] = $occurrence->getId();
            }
        }

        return new BulkUpdateOccurrencesResultDTO(
            updated_count: count($updatedIds),
            updated_ids: $updatedIds,
        );
    }

    /**
     * Returns the status the occurrence should sit at after a capacity edit,
     * or null if the existing status is correct. Mirrors UpdateEventOccurrenceHandler
     * — keep both in sync. Never touches CANCELLED.
     */
    private function reconcileCapacityStatus(
        string $currentStatus,
        ?int $newCapacity,
        int $usedCapacity,
    ): ?string {
        if ($currentStatus === EventOccurrenceStatus::CANCELLED->name) {
            return null;
        }

        if ($newCapacity === null) {
            return $currentStatus === EventOccurrenceStatus::SOLD_OUT->name
                ? EventOccurrenceStatus::ACTIVE->name
                : null;
        }

        $shouldBeSoldOut = $usedCapacity >= $newCapacity;

        if ($shouldBeSoldOut && $currentStatus !== EventOccurrenceStatus::SOLD_OUT->name) {
            return EventOccurrenceStatus::SOLD_OUT->name;
        }

        if (! $shouldBeSoldOut && $currentStatus === EventOccurrenceStatus::SOLD_OUT->name) {
            return EventOccurrenceStatus::ACTIVE->name;
        }

        return null;
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

        // Pin overridden so the next regenerate doesn't revert the shift.
        // Capacity-only changes already flagged via buildUniformAttributes path.
        if ($startEndChanged
            || array_key_exists(EventOccurrenceDomainObjectAbstract::CAPACITY, $attributes)
        ) {
            $attributes[EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN] = true;
        }

        return $attributes;
    }

    private function collectIds(Collection $eligible): array
    {
        return $eligible->map(fn (EventOccurrenceDomainObject $o) => $o->getId())->values()->all();
    }
}
