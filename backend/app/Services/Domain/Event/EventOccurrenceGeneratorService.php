<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventOccurrenceGeneratorService
{
    public function __construct(
        private readonly RecurrenceRuleParserService $ruleParser,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
    ) {
    }

    public function generate(EventDomainObject $event, array $recurrenceRule): Collection
    {
        $candidates = $this->ruleParser->parse($recurrenceRule, $event->getTimezone() ?? 'UTC');

        $existingOccurrences = $this->occurrenceRepository->findWhere([
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $event->getId(),
        ]);

        $existingByStartDate = collect($existingOccurrences)->keyBy(
            fn (EventOccurrenceDomainObject $occ) => $occ->getStartDate()
        );

        $existingIds = collect($existingOccurrences)
            ->map(fn (EventOccurrenceDomainObject $occ) => $occ->getId())
            ->all();
        $occurrenceIdsWithOrders = $this->getOccurrenceIdsWithOrders($existingIds);

        $result = collect();
        $matchedExistingIds = [];

        foreach ($candidates as $candidate) {
            $startDateKey = $candidate['start']->toDateTimeString();
            $existing = $existingByStartDate->get($startDateKey);

            if ($existing) {
                $matchedExistingIds[] = $existing->getId();

                if ($occurrenceIdsWithOrders->contains($existing->getId()) || $existing->getIsOverridden()) {
                    $result->push($existing);
                    continue;
                }

                $this->occurrenceRepository->updateWhere(
                    attributes: [
                        EventOccurrenceDomainObjectAbstract::START_DATE => $candidate['start']->toDateTimeString(),
                        EventOccurrenceDomainObjectAbstract::END_DATE => $candidate['end']?->toDateTimeString(),
                        EventOccurrenceDomainObjectAbstract::CAPACITY => $candidate['capacity'],
                        EventOccurrenceDomainObjectAbstract::LABEL => $candidate['label'] ?? null,
                    ],
                    where: [EventOccurrenceDomainObjectAbstract::ID => $existing->getId()]
                );

                $updated = $this->occurrenceRepository->findById($existing->getId());
                $result->push($updated);
            } else {
                $newOccurrence = $this->occurrenceRepository->create([
                    EventOccurrenceDomainObjectAbstract::EVENT_ID => $event->getId(),
                    EventOccurrenceDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::OCCURRENCE_PREFIX),
                    EventOccurrenceDomainObjectAbstract::START_DATE => $candidate['start']->toDateTimeString(),
                    EventOccurrenceDomainObjectAbstract::END_DATE => $candidate['end']?->toDateTimeString(),
                    EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::ACTIVE->name,
                    EventOccurrenceDomainObjectAbstract::CAPACITY => $candidate['capacity'],
                    EventOccurrenceDomainObjectAbstract::USED_CAPACITY => 0,
                    EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => false,
                    EventOccurrenceDomainObjectAbstract::LABEL => $candidate['label'] ?? null,
                ]);

                $result->push($newOccurrence);
            }
        }

        $this->removeStaleOccurrences($existingOccurrences, $matchedExistingIds, $occurrenceIdsWithOrders);

        return $result;
    }

    private function removeStaleOccurrences(
        Collection $existingOccurrences,
        array $matchedExistingIds,
        Collection $occurrenceIdsWithOrders,
    ): void {
        foreach ($existingOccurrences as $existing) {
            if (in_array($existing->getId(), $matchedExistingIds, true)) {
                continue;
            }

            if ($occurrenceIdsWithOrders->contains($existing->getId()) || $existing->getIsOverridden()) {
                continue;
            }

            $this->occurrenceRepository->deleteWhere(
                [EventOccurrenceDomainObjectAbstract::ID => $existing->getId()]
            );
        }
    }

    private function getOccurrenceIdsWithOrders(array $occurrenceIds): Collection
    {
        if (empty($occurrenceIds)) {
            return collect();
        }

        return DB::table('order_items')
            ->whereIn('event_occurrence_id', $occurrenceIds)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('event_occurrence_id');
    }
}
