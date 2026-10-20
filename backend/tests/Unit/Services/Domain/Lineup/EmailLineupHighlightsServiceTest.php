<?php

namespace Tests\Unit\Services\Domain\Lineup;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpaceRepositoryInterface;
use HiEvents\Services\Domain\Lineup\EmailLineupHighlightsService;
use HiEvents\Services\Domain\Lineup\LineupHighlightSelector;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class EmailLineupHighlightsServiceTest extends TestCase
{
    private function service(array $entries, array $items): EmailLineupHighlightsService
    {
        $entryRepository = m::mock(EventLineupEntryRepositoryInterface::class);
        $entryRepository->shouldReceive('loadRelation')->andReturnSelf();
        $entryRepository->shouldReceive('findWhere')->andReturn(new Collection($entries));

        $itemRepository = m::mock(EventScheduleItemRepositoryInterface::class);
        $itemRepository->shouldReceive('loadRelation')->andReturnSelf();
        $itemRepository->shouldReceive('findWhere')->andReturn(new Collection($items));

        $spaceRepository = m::mock(EventSpaceRepositoryInterface::class);
        $spaceRepository->shouldReceive('findWhere')->andReturn(new Collection([(new EventSpaceDomainObject)->setId(9)->setName('Main Stage')]));

        return new EmailLineupHighlightsService($entryRepository, $itemRepository, $spaceRepository, new LineupHighlightSelector);
    }

    private function entry(int $id, string $name, string $tier): EventLineupEntryDomainObject
    {
        return (new EventLineupEntryDomainObject)->setId($id)->setEventId(1)->setBillingTier($tier)->setIsHidden(false)
            ->setProfile((new ProfileDomainObject)->setId($id)->setName($name));
    }

    private function item(int $id, string $start, array $entryIds, ?string $title = null): EventScheduleItemDomainObject
    {
        return (new EventScheduleItemDomainObject)->setId($id)->setEventSpaceId(9)->setTitle($title)
            ->setStartsAt($start)->setEndsAt($start)
            ->setPerformers(new Collection(array_map(
                fn (int $entryId) => (new EventScheduleItemPerformerDomainObject)->setEventLineupEntryId($entryId),
                $entryIds,
            )));
    }

    private function event(string $type = 'SINGLE', string $category = 'MUSIC'): EventDomainObject
    {
        return (new EventDomainObject)->setId(1)->setTitle('Fest')->setTimezone('Europe/Dublin')
            ->setType($type)->setCategory($category);
    }

    public function test_highlights_headliners_and_their_set_times_without_repeating_names(): void
    {
        $highlights = $this->service(
            [$this->entry(1, 'Nova', 'HEADLINER'), $this->entry(2, 'Opener', 'STANDARD'), $this->entry(3, 'Unscheduled Star', 'HEADLINER')],
            [$this->item(1, '2030-06-14 18:00:00', [2]), $this->item(2, '2030-06-14 21:30:00', [1])],
        )->build($this->event(), (new EventSettingDomainObject)->setHomepageSections(null));

        $this->assertSame('Lineup', $highlights->heading);
        $this->assertSame(['Unscheduled Star'], $highlights->names);
        $this->assertSame([['time' => 'Fri 14 Jun · 10:30 PM', 'title' => 'Nova', 'stage' => 'Main Stage']], $highlights->slots);
        $this->assertStringEndsWith('/event/1/fest#schedule', $highlights->url);
    }

    public function test_recurring_events_only_show_the_attendees_date(): void
    {
        $highlights = $this->service(
            [$this->entry(1, 'Friday Act', 'HEADLINER'), $this->entry(2, 'Saturday Act', 'HEADLINER')],
            [$this->item(1, '2030-06-14 21:00:00', [1]), $this->item(2, '2030-06-15 21:00:00', [2])],
        )->build(
            $this->event(EventType::RECURRING->name, 'BUSINESS'),
            (new EventSettingDomainObject)->setHomepageSections(null),
            (new EventOccurrenceDomainObject)->setStartDate('2030-06-15 18:00:00')->setEndDate('2030-06-15 23:00:00'),
        );

        $this->assertSame('Speakers', $highlights->heading);
        $this->assertSame([], $highlights->names);
        $this->assertSame('Saturday Act', $highlights->slots[0]['title']);
        $this->assertCount(1, $highlights->slots);
    }

    public function test_recurring_orders_spanning_several_dates_get_no_lineup_block(): void
    {
        $highlights = $this->service([$this->entry(1, 'Act', 'HEADLINER')], [$this->item(1, '2030-06-14 21:00:00', [1])])
            ->build($this->event(EventType::RECURRING->name), (new EventSettingDomainObject)->setHomepageSections(null));

        $this->assertNull($highlights);
    }

    public function test_titled_sessions_keep_the_speaker_names_line(): void
    {
        $highlights = $this->service(
            [$this->entry(1, 'Amara', 'HEADLINER')],
            [$this->item(1, '2030-06-14 09:30:00', [1], 'Opening keynote')],
        )->build($this->event(category: 'TECH'), (new EventSettingDomainObject)->setHomepageSections(null));

        $this->assertSame(['Amara'], $highlights->names);
        $this->assertSame('Opening keynote', $highlights->slots[0]['title']);
    }

    public function test_only_the_lineup_is_shown_when_the_schedule_section_is_off(): void
    {
        $highlights = $this->service(
            [$this->entry(1, 'Nova', 'HEADLINER')],
            [$this->item(1, '2030-06-14 21:30:00', [1])],
        )->build($this->event(), (new EventSettingDomainObject)->setHomepageSections([
            ['id' => 'schedule', 'type' => 'SCHEDULE', 'enabled' => false],
        ]));

        $this->assertSame(['Nova'], $highlights->names);
        $this->assertSame([], $highlights->slots);
        $this->assertStringEndsWith('#lineup', $highlights->url);
    }

    public function test_nothing_is_shown_when_both_lineup_sections_are_turned_off(): void
    {
        $highlights = $this->service([$this->entry(1, 'Nova', 'HEADLINER')], [])->build(
            $this->event(),
            (new EventSettingDomainObject)->setHomepageSections([
                ['id' => 'lineup', 'type' => 'LINEUP', 'enabled' => false],
                ['id' => 'schedule', 'type' => 'SCHEDULE', 'enabled' => false],
            ]),
        );

        $this->assertNull($highlights);
    }
}
