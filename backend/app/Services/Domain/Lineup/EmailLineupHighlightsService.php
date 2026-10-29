<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Lineup;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\HomepageSectionType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\DomainObjects\Generated\EventLineupEntryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventScheduleItemDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSpaceDomainObjectAbstract;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpaceRepositoryInterface;
use HiEvents\Services\Domain\Lineup\DTO\EmailLineupHighlightsDTO;
use Illuminate\Support\Collection;

class EmailLineupHighlightsService
{
    private const SPEAKER_CATEGORIES = ['BUSINESS', 'TECH', 'EDUCATION'];

    private const HOST_CATEGORIES = ['WORKSHOP', 'WELLNESS', 'SPIRITUALITY', 'SPORTS', 'HOBBIES'];

    public function __construct(
        private readonly EventLineupEntryRepositoryInterface $lineupEntryRepository,
        private readonly EventScheduleItemRepositoryInterface $scheduleItemRepository,
        private readonly EventSpaceRepositoryInterface $spaceRepository,
        private readonly LineupHighlightSelector $highlightSelector,
    ) {}

    public function toTemplateContext(?EmailLineupHighlightsDTO $highlights): array
    {
        return [
            'heading' => $highlights?->heading ?? '',
            'names' => $highlights?->names ?? [],
            'slots' => $highlights?->slots ?? [],
            'url' => $highlights?->url ?? '',
        ];
    }

    public function build(
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        ?EventOccurrenceDomainObject $occurrence = null,
    ): ?EmailLineupHighlightsDTO {
        if ($event->isRecurring() && $occurrence === null) {
            return null;
        }

        $showLineup = HomepageSectionType::LINEUP->isEnabledIn($eventSettings->getHomepageSections());
        $showSchedule = HomepageSectionType::SCHEDULE->isEnabledIn($eventSettings->getHomepageSections());

        if (! $showLineup && ! $showSchedule) {
            return null;
        }

        $entries = $this->visibleEntries($event->getId());

        if ($entries->isEmpty()) {
            return null;
        }

        $timezone = $event->getTimezone() ?? 'UTC';
        $occurrence = $event->isRecurring() ? $occurrence : null;
        $allItems = $this->scheduleItems($event->getId());
        $items = $this->itemsForOccurrence($allItems, $occurrence, $timezone);
        $entries = $occurrence === null ? $entries : $this->entriesForOccurrence($entries, $items, $allItems);
        $selection = $this->highlightSelector->select($entries, $showSchedule ? $items : collect());
        $slots = $this->formatSlots($event->getId(), $selection->slots, $timezone);
        $names = $showLineup
            ? $selection->entries
                ->map(fn (EventLineupEntryDomainObject $entry) => $entry->getProfile()->getName())
                ->values()
                ->all()
            : [];

        if ($names === [] && $slots === []) {
            return null;
        }

        return new EmailLineupHighlightsDTO(
            heading: $this->heading($event),
            names: $names,
            slots: $slots,
            url: $event->getEventUrl().($slots === [] ? '#lineup' : '#schedule'),
        );
    }

    private function visibleEntries(int $eventId): Collection
    {
        return $this->lineupEntryRepository
            ->loadRelation(new Relationship(ProfileDomainObject::class, name: 'profile'))
            ->findWhere(
                where: [
                    EventLineupEntryDomainObjectAbstract::EVENT_ID => $eventId,
                    EventLineupEntryDomainObjectAbstract::IS_HIDDEN => false,
                ],
                orderAndDirections: [
                    new OrderAndDirection(EventLineupEntryDomainObjectAbstract::SORT_ORDER),
                    new OrderAndDirection(EventLineupEntryDomainObjectAbstract::ID),
                ],
            )
            ->filter(fn (EventLineupEntryDomainObject $entry) => $entry->getProfile() !== null)
            ->values();
    }

    private function scheduleItems(int $eventId): Collection
    {
        return $this->scheduleItemRepository
            ->loadRelation(new Relationship(EventScheduleItemPerformerDomainObject::class, name: 'performers'))
            ->findWhere(
                where: [EventScheduleItemDomainObjectAbstract::EVENT_ID => $eventId],
                orderAndDirections: [
                    new OrderAndDirection(EventScheduleItemDomainObjectAbstract::STARTS_AT),
                    new OrderAndDirection(EventScheduleItemDomainObjectAbstract::ID),
                ],
            );
    }

    private function itemsForOccurrence(Collection $items, ?EventOccurrenceDomainObject $occurrence, string $timezone): Collection
    {
        if ($occurrence === null) {
            return $items;
        }

        $firstDay = ScheduleDay::keyForUtc($occurrence->getStartDate(), $timezone);
        $lastDay = ScheduleDay::keyForUtc($occurrence->getEndDate() ?? $occurrence->getStartDate(), $timezone);

        return $items
            ->filter(function (EventScheduleItemDomainObject $item) use ($firstDay, $lastDay, $timezone) {
                $day = ScheduleDay::keyForUtc($item->getStartsAt(), $timezone);

                return $day >= $firstDay && $day <= $lastDay;
            })
            ->values();
    }

    private function entriesForOccurrence(Collection $entries, Collection $items, Collection $allItems): Collection
    {
        $scheduledOnDate = $this->performerIds($items);
        $scheduledAnywhere = $this->performerIds($allItems);

        return $entries
            ->filter(fn (EventLineupEntryDomainObject $entry) => in_array($entry->getId(), $scheduledOnDate, true)
                || ! in_array($entry->getId(), $scheduledAnywhere, true))
            ->values();
    }

    /**
     * @return array<int, array{time: string, title: string, stage: ?string}>
     */
    private function formatSlots(int $eventId, Collection $slotRows, string $timezone): array
    {
        if ($slotRows->isEmpty()) {
            return [];
        }

        $spacesById = $this->spaceRepository
            ->findWhere([EventSpaceDomainObjectAbstract::EVENT_ID => $eventId])
            ->keyBy(fn (EventSpaceDomainObject $space) => $space->getId());

        return $slotRows
            ->map(fn (array $row) => [
                'time' => CarbonImmutable::parse($row['item']->getStartsAt(), 'UTC')
                    ->setTimezone($timezone)
                    ->translatedFormat('D j M · g:i A'),
                'title' => $row['item']->getTitle() ?? $row['people']
                    ->map(fn (EventLineupEntryDomainObject $entry) => $entry->getProfile()->getName())
                    ->implode(', '),
                'stage' => $spacesById->get($row['item']->getEventSpaceId() ?? '')?->getName(),
            ])
            ->values()
            ->all();
    }

    private function performerIds(Collection $items): array
    {
        return $items
            ->flatMap(fn (EventScheduleItemDomainObject $item) => ($item->getPerformers() ?? collect())
                ->map(fn (EventScheduleItemPerformerDomainObject $performer) => $performer->getEventLineupEntryId()))
            ->unique()
            ->values()
            ->all();
    }

    private function heading(EventDomainObject $event): string
    {
        return match (true) {
            in_array($event->getCategory(), self::SPEAKER_CATEGORIES, true) => __('Speakers'),
            in_array($event->getCategory(), self::HOST_CATEGORIES, true) => __('Hosts'),
            default => __('Lineup'),
        };
    }
}
