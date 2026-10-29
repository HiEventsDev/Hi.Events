<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Lineup;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\DomainObjects\Generated\EventLineupEntryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventLocationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventScheduleItemDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventScheduleItemPerformerDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSpaceDomainObjectAbstract;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventLocationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemPerformerRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpaceRepositoryInterface;

class EventLineupCloneService
{
    public function __construct(
        private readonly EventLineupEntryRepositoryInterface $lineupEntryRepository,
        private readonly EventSpaceRepositoryInterface $spaceRepository,
        private readonly EventScheduleItemRepositoryInterface $scheduleItemRepository,
        private readonly EventScheduleItemPerformerRepositoryInterface $performerRepository,
        private readonly EventLocationRepositoryInterface $eventLocationRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
    ) {}

    public function clone(int $sourceEventId, int $newEventId, bool $shiftToNewStartDate): void
    {
        $entryMap = $this->cloneEntries($sourceEventId, $newEventId);
        $spaceMap = $this->cloneSpaces($sourceEventId, $newEventId);
        $offsetSeconds = $shiftToNewStartDate ? $this->startOffsetSeconds($sourceEventId, $newEventId) : 0;

        $items = $this->scheduleItemRepository
            ->loadRelation(new Relationship(EventScheduleItemPerformerDomainObject::class, name: 'performers'))
            ->findWhere([EventScheduleItemDomainObjectAbstract::EVENT_ID => $sourceEventId]);

        foreach ($items as $item) {
            /** @var EventScheduleItemDomainObject $item */
            $newItemId = $this->scheduleItemRepository->create([
                EventScheduleItemDomainObjectAbstract::EVENT_ID => $newEventId,
                EventScheduleItemDomainObjectAbstract::EVENT_SPACE_ID => $item->getEventSpaceId() === null ? null : $spaceMap[$item->getEventSpaceId()] ?? null,
                EventScheduleItemDomainObjectAbstract::TITLE => $item->getTitle(),
                EventScheduleItemDomainObjectAbstract::DESCRIPTION => $item->getDescription(),
                EventScheduleItemDomainObjectAbstract::STARTS_AT => $this->shift($item->getStartsAt(), $offsetSeconds),
                EventScheduleItemDomainObjectAbstract::ENDS_AT => $this->shift($item->getEndsAt(), $offsetSeconds),
            ])->getId();

            $performerRows = ($item->getPerformers() ?? collect())
                ->map(fn (EventScheduleItemPerformerDomainObject $performer) => [
                    EventScheduleItemPerformerDomainObjectAbstract::EVENT_SCHEDULE_ITEM_ID => $newItemId,
                    EventScheduleItemPerformerDomainObjectAbstract::EVENT_LINEUP_ENTRY_ID => $entryMap[$performer->getEventLineupEntryId()] ?? null,
                    EventScheduleItemPerformerDomainObjectAbstract::SORT_ORDER => $performer->getSortOrder(),
                ])
                ->filter(fn (array $row) => $row[EventScheduleItemPerformerDomainObjectAbstract::EVENT_LINEUP_ENTRY_ID] !== null)
                ->values()
                ->all();

            if ($performerRows !== []) {
                $this->performerRepository->insert($performerRows);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function cloneEntries(int $sourceEventId, int $newEventId): array
    {
        $map = [];

        foreach ($this->lineupEntryRepository->findWhere([EventLineupEntryDomainObjectAbstract::EVENT_ID => $sourceEventId]) as $entry) {
            /** @var EventLineupEntryDomainObject $entry */
            $map[$entry->getId()] = $this->lineupEntryRepository->create([
                EventLineupEntryDomainObjectAbstract::EVENT_ID => $newEventId,
                EventLineupEntryDomainObjectAbstract::PROFILE_ID => $entry->getProfileId(),
                EventLineupEntryDomainObjectAbstract::BILLING_TIER => $entry->getBillingTier(),
                EventLineupEntryDomainObjectAbstract::ROLE_LABEL => $entry->getRoleLabel(),
                EventLineupEntryDomainObjectAbstract::SORT_ORDER => $entry->getSortOrder(),
                EventLineupEntryDomainObjectAbstract::IS_HIDDEN => $entry->getIsHidden(),
            ])->getId();
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    private function cloneSpaces(int $sourceEventId, int $newEventId): array
    {
        $map = [];
        $spaces = $this->spaceRepository
            ->loadRelation(new Relationship(EventLocationDomainObject::class, name: 'event_location'))
            ->findWhere(
                where: [EventSpaceDomainObjectAbstract::EVENT_ID => $sourceEventId],
                orderAndDirections: [new OrderAndDirection(EventSpaceDomainObjectAbstract::ID)],
            );

        foreach ($spaces as $space) {
            /** @var EventSpaceDomainObject $space */
            $map[$space->getId()] = $this->spaceRepository->create([
                EventSpaceDomainObjectAbstract::EVENT_ID => $newEventId,
                EventSpaceDomainObjectAbstract::NAME => $space->getName(),
                EventSpaceDomainObjectAbstract::EVENT_LOCATION_ID => $this->matchingVenueId($space, $newEventId),
            ])->getId();
        }

        return $map;
    }

    private function matchingVenueId(EventSpaceDomainObject $space, int $newEventId): ?int
    {
        $locationId = $space->getEventLocation()?->getLocationId();

        if ($locationId === null) {
            return null;
        }

        return $this->eventLocationRepository->findFirstWhere([
            EventLocationDomainObjectAbstract::EVENT_ID => $newEventId,
            EventLocationDomainObjectAbstract::LOCATION_ID => $locationId,
        ])?->getId();
    }

    private function startOffsetSeconds(int $sourceEventId, int $newEventId): int
    {
        $sourceStart = $this->earliestOccurrenceStart($sourceEventId);
        $newStart = $this->earliestOccurrenceStart($newEventId);

        if ($sourceStart === null || $newStart === null) {
            return 0;
        }

        return (int) $sourceStart->diffInSeconds($newStart, false);
    }

    private function earliestOccurrenceStart(int $eventId): ?Carbon
    {
        $occurrence = $this->occurrenceRepository->findWhere(
            where: [EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId],
            orderAndDirections: [new OrderAndDirection(EventOccurrenceDomainObjectAbstract::START_DATE)],
            limit: 1,
        )->first();

        return $occurrence === null ? null : Carbon::parse($occurrence->getStartDate(), 'UTC');
    }

    private function shift(string $utcDate, int $offsetSeconds): string
    {
        return Carbon::parse($utcDate, 'UTC')->addSeconds($offsetSeconds)->toDateTimeString();
    }
}
