<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\BulkOccurrenceAction;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Jobs\Occurrence\BulkCancelOccurrencesJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\BulkUpdateOccurrencesDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Throwable;

class BulkUpdateOccurrencesHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly OrderItemRepositoryInterface       $orderItemRepository,
        private readonly DatabaseManager                    $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(BulkUpdateOccurrencesDTO $dto): int
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
                BulkOccurrenceAction::DELETE => $this->handleDelete($eligible),
                BulkOccurrenceAction::UPDATE => $this->handleUpdate($dto, $eligible),
            };
        });
    }

    private function filterEligible(Collection $occurrences, BulkUpdateOccurrencesDTO $dto): Collection
    {
        return $occurrences->filter(function (EventOccurrenceDomainObject $occurrence) use ($dto) {
            if (!empty($dto->occurrence_ids) && !in_array($occurrence->getId(), $dto->occurrence_ids, true)) {
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

    private function handleCancel(BulkUpdateOccurrencesDTO $dto, Collection $eligible): int
    {
        $ids = $this->collectIds($eligible);

        if (!empty($ids)) {
            BulkCancelOccurrencesJob::dispatch($dto->event_id, $ids, $dto->refund_orders);
        }

        return count($ids);
    }

    private function handleDelete(Collection $eligible): int
    {
        $deletableIds = [];

        foreach ($eligible as $occurrence) {
            $hasOrders = $this->orderItemRepository->countWhere([
                'event_occurrence_id' => $occurrence->getId(),
            ]) > 0;

            if (!$hasOrders) {
                $deletableIds[] = $occurrence->getId();
            }
        }

        if (!empty($deletableIds)) {
            $this->occurrenceRepository->deleteWhere([
                [EventOccurrenceDomainObjectAbstract::ID, 'in', $deletableIds],
            ]);
        }

        return count($deletableIds);
    }

    private function handleUpdate(BulkUpdateOccurrencesDTO $dto, Collection $eligible): int
    {
        $requiresPerRow = $dto->start_time_shift !== null
            || $dto->end_time_shift !== null
            || $dto->duration_minutes !== null;

        if ($requiresPerRow) {
            return $this->applyPerRowUpdate($dto, $eligible);
        }

        return $this->applyUniformUpdate($dto, $eligible);
    }

    private function applyUniformUpdate(BulkUpdateOccurrencesDTO $dto, Collection $eligible): int
    {
        $attributes = $this->buildUniformAttributes($dto);

        if (empty($attributes)) {
            return 0;
        }

        $ids = $this->collectIds($eligible);

        if (empty($ids)) {
            return 0;
        }

        $this->occurrenceRepository->updateWhere(
            attributes: $attributes,
            where: [
                [EventOccurrenceDomainObjectAbstract::ID, 'in', $ids],
            ],
        );

        return count($ids);
    }

    private function applyPerRowUpdate(BulkUpdateOccurrencesDTO $dto, Collection $eligible): int
    {
        $updatedCount = 0;

        foreach ($eligible as $occurrence) {
            $attributes = $this->buildPerRowAttributes($dto, $occurrence);

            if (!empty($attributes)) {
                $this->occurrenceRepository->updateWhere(
                    attributes: $attributes,
                    where: [EventOccurrenceDomainObjectAbstract::ID => $occurrence->getId()],
                );
                $updatedCount++;
            }
        }

        return $updatedCount;
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

        if ($dto->start_time_shift !== null && $dto->start_time_shift !== 0) {
            $start = Carbon::parse($occurrence->getStartDate(), 'UTC');
            $start->addMinutes($dto->start_time_shift);
            $attributes[EventOccurrenceDomainObjectAbstract::START_DATE] = $start->toDateTimeString();
        }

        if ($dto->end_time_shift !== null && $dto->end_time_shift !== 0 && $occurrence->getEndDate() !== null) {
            $end = Carbon::parse($occurrence->getEndDate(), 'UTC');
            $end->addMinutes($dto->end_time_shift);
            $attributes[EventOccurrenceDomainObjectAbstract::END_DATE] = $end->toDateTimeString();
        }

        if ($dto->duration_minutes !== null) {
            $startDate = $attributes[EventOccurrenceDomainObjectAbstract::START_DATE] ?? $occurrence->getStartDate();
            $start = Carbon::parse($startDate, 'UTC');
            $attributes[EventOccurrenceDomainObjectAbstract::END_DATE] = $start->copy()->addMinutes($dto->duration_minutes)->toDateTimeString();
        }

        return $attributes;
    }

    private function collectIds(Collection $eligible): array
    {
        return $eligible->map(fn(EventOccurrenceDomainObject $o) => $o->getId())->values()->all();
    }
}
