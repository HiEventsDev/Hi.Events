<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\Enums\HomepageSectionType;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\Generated\EventLineupEntryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventLocationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventScheduleItemDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSpaceDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventLocationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpaceRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\DTO\EventLineupDTO;
use HiEvents\Services\Domain\Lineup\ScheduleDay;
use Illuminate\Support\Collection;

class GetEventLineupHandler
{
    public function __construct(
        private readonly EventLineupEntryRepositoryInterface $lineupEntryRepository,
        private readonly EventSpaceRepositoryInterface $spaceRepository,
        private readonly EventScheduleItemRepositoryInterface $scheduleItemRepository,
        private readonly EventLocationRepositoryInterface $eventLocationRepository,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public function handle(int $eventId, bool $forPublic): EventLineupDTO
    {
        $entries = $this->lineupEntryRepository
            ->loadRelation(new Relationship(ProfileDomainObject::class, nested: [
                new Relationship(ImageDomainObject::class),
            ], name: 'profile'))
            ->findWhere(
                where: [EventLineupEntryDomainObjectAbstract::EVENT_ID => $eventId],
                orderAndDirections: [
                    new OrderAndDirection(EventLineupEntryDomainObjectAbstract::SORT_ORDER),
                    new OrderAndDirection(EventLineupEntryDomainObjectAbstract::ID),
                ],
            )
            ->filter(fn (EventLineupEntryDomainObject $entry) => $entry->getProfile() !== null)
            ->values();

        $spaces = $this->spaceRepository
            ->loadRelation(new Relationship(EventLocationDomainObject::class, nested: [
                new Relationship(LocationDomainObject::class, name: 'location'),
            ], name: 'event_location'))
            ->findWhere(
                where: [EventSpaceDomainObjectAbstract::EVENT_ID => $eventId],
                orderAndDirections: [new OrderAndDirection(EventSpaceDomainObjectAbstract::ID)],
            );

        $scheduleItems = $this->scheduleItemRepository
            ->loadRelation(new Relationship(EventScheduleItemPerformerDomainObject::class, name: 'performers'))
            ->findWhere(
                where: [EventScheduleItemDomainObjectAbstract::EVENT_ID => $eventId],
                orderAndDirections: [
                    new OrderAndDirection(EventScheduleItemDomainObjectAbstract::STARTS_AT),
                    new OrderAndDirection(EventScheduleItemDomainObjectAbstract::ID),
                ],
            );

        if ($forPublic) {
            return $this->publicLineup($eventId, $entries, $spaces, $scheduleItems);
        }

        return new EventLineupDTO(
            entries: $entries,
            spaces: $spaces,
            schedule_items: $scheduleItems,
            venues: $this->eventLocationRepository
                ->loadRelation(new Relationship(LocationDomainObject::class, name: 'location'))
                ->findWhere(
                    where: [EventLocationDomainObjectAbstract::EVENT_ID => $eventId],
                    orderAndDirections: [new OrderAndDirection(EventLocationDomainObjectAbstract::ID)],
                )
                ->filter(fn (EventLocationDomainObject $venue) => $venue->getLocation() !== null)
                ->values(),
        );
    }

    private function publicLineup(int $eventId, Collection $entries, Collection $spaces, Collection $scheduleItems): EventLineupDTO
    {
        $sections = $this->eventSettingsRepository
            ->findFirstWhere([EventSettingDomainObjectAbstract::EVENT_ID => $eventId])
            ?->getHomepageSections();
        $showLineup = HomepageSectionType::LINEUP->isEnabledIn($sections);
        $showSchedule = HomepageSectionType::SCHEDULE->isEnabledIn($sections);

        $visibleEntries = $entries
            ->reject(fn (EventLineupEntryDomainObject $entry) => $entry->getIsHidden())
            ->values();

        $visibleEntryIds = $visibleEntries->map(fn (EventLineupEntryDomainObject $entry) => $entry->getId())->all();

        $visibleItems = ! $showSchedule ? collect() : $scheduleItems
            ->map(fn (EventScheduleItemDomainObject $item) => $item->setPerformers(
                ($item->getPerformers() ?? collect())
                    ->filter(fn (EventScheduleItemPerformerDomainObject $performer) => in_array(
                        $performer->getEventLineupEntryId(),
                        $visibleEntryIds,
                        true,
                    ))
                    ->values()
            ))
            ->filter(fn (EventScheduleItemDomainObject $item) => $item->getTitle() !== null || $item->getPerformers()->isNotEmpty())
            ->values();

        if (! $showLineup) {
            $scheduledIds = $visibleItems
                ->flatMap(fn (EventScheduleItemDomainObject $item) => $item->getPerformers()
                    ->map(fn (EventScheduleItemPerformerDomainObject $performer) => $performer->getEventLineupEntryId()))
                ->all();
            $visibleEntries = $visibleEntries
                ->filter(fn (EventLineupEntryDomainObject $entry) => in_array($entry->getId(), $scheduledIds, true))
                ->values();
        }

        return new EventLineupDTO(
            entries: $visibleEntries,
            spaces: $showSchedule ? $spaces : collect(),
            schedule_items: $visibleItems,
            schedule_days_by_entry_id: $showSchedule ? null : $this->scheduleDaysByEntryId($eventId, $visibleEntries, $scheduleItems),
        );
    }

    /**
     * @return array<int, list<string>>|null
     */
    private function scheduleDaysByEntryId(int $eventId, Collection $entries, Collection $scheduleItems): ?array
    {
        $event = $this->eventRepository->findById($eventId);

        if (! $event->isRecurring()) {
            return null;
        }

        $days = $entries->mapWithKeys(fn (EventLineupEntryDomainObject $entry) => [$entry->getId() => []])->all();

        if ($days === [] || $scheduleItems->isEmpty()) {
            return $days;
        }

        $timezone = $event->getTimezone() ?? 'UTC';

        foreach ($scheduleItems as $item) {
            $day = ScheduleDay::keyForUtc($item->getStartsAt(), $timezone);
            foreach ($item->getPerformers() ?? [] as $performer) {
                if (array_key_exists($performer->getEventLineupEntryId(), $days)) {
                    $days[$performer->getEventLineupEntryId()][$day] = $day;
                }
            }
        }

        return array_map(fn (array $entryDays) => collect($entryDays)->sort()->values()->all(), $days);
    }
}
