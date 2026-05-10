<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use Illuminate\Database\DatabaseManager;
use Throwable;

class UpdateEventOccurrenceHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(int $occurrenceId, UpsertEventOccurrenceDTO $dto): EventOccurrenceDomainObject
    {
        return $this->databaseManager->transaction(function () use ($occurrenceId, $dto) {
            $occurrence = $this->occurrenceRepository->findFirstWhere([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $dto->event_id,
            ]);

            if (! $occurrence) {
                throw new ResourceNotFoundException(
                    __('Occurrence :id not found for event :eventId', [
                        'id' => $occurrenceId,
                        'eventId' => $dto->event_id,
                    ])
                );
            }

            // Only flag as overridden when a rule-determining field actually changes
            // away from what the rule would produce. Label edits and no-op saves
            // shouldn't pin the row against rule regenerates. Dates are normalized
            // to a canonical parsed form — `DateHelper::convertToUTC` returns a
            // different string format than the DB-hydrated getStartDate(), so
            // string comparison alone would always register as changed.
            $isOverride = $occurrence->getIsOverridden()
                || $this->datesDiffer($dto->start_date, $occurrence->getStartDate())
                || $this->datesDiffer($dto->end_date, $occurrence->getEndDate())
                || $dto->capacity !== $occurrence->getCapacity();

            // CANCELLED is intentionally not writable here — lifecycle
            // transitions (cancel / reactivate) live in their own handlers
            // and fan out into refund / attendee / recurrence-exclusion side
            // effects this handler does not perform. SOLD_OUT/ACTIVE on the
            // other hand are capacity-derived: ProductQuantityUpdateService
            // flips between them whenever used_capacity crosses capacity, so
            // a capacity edit here has to do the same reconciliation or a
            // sold-out date stays blocked after capacity is increased / cleared,
            // and an over-capacity active date stays visually open.
            $attributes = [
                EventOccurrenceDomainObjectAbstract::START_DATE => $dto->start_date,
                EventOccurrenceDomainObjectAbstract::END_DATE => $dto->end_date,
                EventOccurrenceDomainObjectAbstract::CAPACITY => $dto->capacity,
                EventOccurrenceDomainObjectAbstract::LABEL => $dto->label,
                EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => $isOverride,
            ];

            $reconciledStatus = $this->reconcileCapacityStatus(
                currentStatus: $occurrence->getStatus(),
                newCapacity: $dto->capacity,
                usedCapacity: $occurrence->getUsedCapacity() ?? 0,
            );
            if ($reconciledStatus !== null) {
                $attributes[EventOccurrenceDomainObjectAbstract::STATUS] = $reconciledStatus;
            }

            return $this->occurrenceRepository->updateFromArray(
                id: $occurrence->getId(),
                attributes: $attributes,
            );
        });
    }

    /**
     * Returns the status the occurrence should sit at after a capacity edit,
     * or null if the existing status is correct. Only flips between ACTIVE
     * and SOLD_OUT — never touches CANCELLED, which is owned by the dedicated
     * cancel/reactivate handlers.
     */
    private function reconcileCapacityStatus(
        string $currentStatus,
        ?int $newCapacity,
        int $usedCapacity,
    ): ?string {
        if ($currentStatus === EventOccurrenceStatus::CANCELLED->name) {
            return null;
        }

        // Unlimited (null) capacity can never be sold out.
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

    private function datesDiffer(?string $a, ?string $b): bool
    {
        if ($a === null && $b === null) {
            return false;
        }
        if ($a === null || $b === null) {
            return true;
        }

        return ! Carbon::parse($a, 'UTC')->equalTo(Carbon::parse($b, 'UTC'));
    }
}
