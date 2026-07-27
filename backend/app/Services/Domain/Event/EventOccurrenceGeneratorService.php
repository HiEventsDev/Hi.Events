<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Event;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventOccurrenceGeneratorService
{
    public function __construct(
        private readonly RecurrenceRuleParserService $ruleParser,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
    ) {}

    public function generate(EventDomainObject $event, array $recurrenceRule): Collection
    {
        $candidates = $this->ruleParser->parse($recurrenceRule, $event->getTimezone() ?? 'UTC');

        $existingOccurrences = $this->occurrenceRepository->findWhere([
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $event->getId(),
        ]);

        $existingByStartDate = collect($existingOccurrences)->keyBy(
            fn (EventOccurrenceDomainObject $occ) => Carbon::parse($occ->getStartDate())->utc()->toDateTimeString()
        );

        $existingIds = collect($existingOccurrences)
            ->map(fn (EventOccurrenceDomainObject $occ) => $occ->getId())
            ->all();
        $occurrenceIdsInUse = $this->getOccurrenceIdsInUse($existingIds);

        $result = collect();
        $matchedExistingIds = [];

        foreach ($candidates as $candidate) {
            $startDateKey = $candidate['start']->copy()->utc()->toDateTimeString();

            $existing = $existingByStartDate->get($startDateKey);

            if ($existing) {
                $matchedExistingIds[] = $existing->getId();

                if ($occurrenceIdsInUse->contains($existing->getId()) || $existing->getIsOverridden()) {
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

        $this->removeStaleOccurrences($existingOccurrences, $matchedExistingIds, $occurrenceIdsInUse);

        return $result;
    }

    private function removeStaleOccurrences(
        Collection $existingOccurrences,
        array $matchedExistingIds,
        Collection $occurrenceIdsInUse,
    ): void {
        $idsToDelete = [];
        $eventIdsToDelete = [];

        foreach ($existingOccurrences as $existing) {
            if (in_array($existing->getId(), $matchedExistingIds, true)) {
                continue;
            }

            if ($existing->getIsOverridden()) {
                continue;
            }

            if ($occurrenceIdsInUse->contains($existing->getId())) {
                $this->occurrenceRepository->updateWhere(
                    attributes: [
                        EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => true,
                    ],
                    where: [EventOccurrenceDomainObjectAbstract::ID => $existing->getId()]
                );

                continue;
            }

            if ($existing->getStatus() === EventOccurrenceStatus::CANCELLED->name) {
                continue;
            }

            $idsToDelete[] = $existing->getId();
            $eventIdsToDelete[$existing->getEventId()] = true;
        }

        if ($idsToDelete === []) {
            return;
        }

        $this->waitlistEntryRepository->updateWhere(
            attributes: [
                'status' => WaitlistEntryStatus::CANCELLED->name,
            ],
            where: [
                ['event_id', 'in', array_keys($eventIdsToDelete)],
                ['event_occurrence_id', 'in', $idsToDelete],
                ['status', 'in', [
                    WaitlistEntryStatus::WAITING->name,
                    WaitlistEntryStatus::OFFERED->name,
                ]],
            ],
        );

        $this->occurrenceRepository->deleteWhere([
            [EventOccurrenceDomainObjectAbstract::ID, 'in', $idsToDelete],
        ]);
    }

    private function getOccurrenceIdsInUse(array $occurrenceIds): Collection
    {
        if (empty($occurrenceIds)) {
            return collect();
        }

        $withOrderItems = DB::table('order_items')
            ->whereIn('event_occurrence_id', $occurrenceIds)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('event_occurrence_id');

        $withAttendees = DB::table('attendees')
            ->whereIn('event_occurrence_id', $occurrenceIds)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('event_occurrence_id');

        return $withOrderItems->merge($withAttendees)->unique()->values();
    }
}
