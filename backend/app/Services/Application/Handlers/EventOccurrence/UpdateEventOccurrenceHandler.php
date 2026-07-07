<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use HiEvents\Services\Domain\EventLocation\EventLocationCleaner;
use HiEvents\Services\Domain\EventLocation\EventLocationUpserter;
use Illuminate\Database\DatabaseManager;
use Throwable;

class UpdateEventOccurrenceHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventLocationUpserter $eventLocationUpserter,
        private readonly EventLocationCleaner $eventLocationCleaner,
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

            $previousEventLocationId = $occurrence->getEventLocationId();
            $newEventLocationId = $previousEventLocationId;
            $eventLocationChanged = false;

            if ($dto->event_location !== null) {
                $event = $this->eventRepository->findById($dto->event_id);
                if ($event === null) {
                    throw new ResourceNotFoundException(__('Event :id not found', ['id' => $dto->event_id]));
                }

                if ($previousEventLocationId === null) {
                    $eventLocation = $this->eventLocationUpserter->createForEvent(
                        eventId: $dto->event_id,
                        accountId: $event->getAccountId(),
                        data: $dto->event_location,
                    );
                    $newEventLocationId = $eventLocation->getId();
                    $eventLocationChanged = true;
                } else {
                    $this->eventLocationUpserter->updateInPlace(
                        eventLocationId: $previousEventLocationId,
                        eventId: $dto->event_id,
                        accountId: $event->getAccountId(),
                        data: $dto->event_location,
                    );
                }
            } elseif ($dto->clear_event_location && $previousEventLocationId !== null) {
                $newEventLocationId = null;
                $eventLocationChanged = true;
            }

            // `DateHelper::convertToUTC` normalizes to a different string than the
            // DB-hydrated value, so string compare alone always reports a change.
            $isOverride = $occurrence->getIsOverridden()
                || $this->datesDiffer($dto->start_date, $occurrence->getStartDate())
                || $this->datesDiffer($dto->end_date, $occurrence->getEndDate())
                || $dto->capacity !== $occurrence->getCapacity()
                || $eventLocationChanged;

            $attributes = [
                EventOccurrenceDomainObjectAbstract::START_DATE => $dto->start_date,
                EventOccurrenceDomainObjectAbstract::END_DATE => $dto->end_date,
                EventOccurrenceDomainObjectAbstract::CAPACITY => $dto->capacity,
                EventOccurrenceDomainObjectAbstract::LABEL => $dto->label,
                EventOccurrenceDomainObjectAbstract::SHOW_AVAILABLE_CAPACITY => $dto->show_available_capacity,
                EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => $isOverride,
                EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID => $newEventLocationId,
            ];

            $reconciledStatus = $this->reconcileCapacityStatus(
                currentStatus: $occurrence->getStatus(),
                newCapacity: $dto->capacity,
                usedCapacity: $occurrence->getUsedCapacity() ?? 0,
            );
            if ($reconciledStatus !== null) {
                $attributes[EventOccurrenceDomainObjectAbstract::STATUS] = $reconciledStatus;
            }

            $updated = $this->occurrenceRepository->updateFromArray(
                id: $occurrence->getId(),
                attributes: $attributes,
            );

            if ($dto->clear_event_location && $previousEventLocationId !== null) {
                $this->eventLocationCleaner->deleteIfOrphaned($previousEventLocationId);
            }

            return $updated;
        });
    }

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
