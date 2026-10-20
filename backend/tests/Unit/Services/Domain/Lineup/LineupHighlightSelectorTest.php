<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Lineup;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Services\Domain\Lineup\LineupHighlightSelector;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LineupHighlightSelectorTest extends TestCase
{
    public function test_picks_headliners_first_and_caps_the_names_keeping_the_callers_keys(): void
    {
        $entries = [3 => $this->entry(1, 'FEATURED')];

        foreach (range(2, 9) as $id) {
            $entries[$id + 2] = $this->entry($id, 'HEADLINER');
        }

        $selection = (new LineupHighlightSelector)->select(new Collection($entries), new Collection);

        $this->assertSame([4, 5, 6, 7, 8, 9], $selection->entries->keys()->all());
        $this->assertSame([2, 3, 4, 5, 6, 7], $selection->entries->map->getId()->values()->all());
        $this->assertCount(0, $selection->slots);
    }

    public function test_falls_back_to_featured_then_everyone(): void
    {
        $selector = new LineupHighlightSelector;

        $featured = $selector->select(new Collection([$this->entry(1, 'STANDARD'), $this->entry(2, 'FEATURED')]), new Collection);
        $everyone = $selector->select(new Collection([$this->entry(1, 'STANDARD'), $this->entry(2, 'STANDARD')]), new Collection);

        $this->assertSame([2], $featured->entries->map->getId()->values()->all());
        $this->assertSame([1, 2], $everyone->entries->map->getId()->values()->all());
    }

    public function test_prefers_slots_featuring_highlighted_people_and_leaves_names_shown_in_untitled_slots_out_of_the_names(): void
    {
        $entries = new Collection([$this->entry(1, 'HEADLINER'), $this->entry(2, 'HEADLINER'), $this->entry(3, 'STANDARD')]);
        $items = new Collection([
            $this->item(10, 'Doors'),
            $this->item(11, null, 3),
            $this->item(12, null, 1, 3),
            ...array_map(fn (int $id) => $this->item($id, "Set $id", 2), range(13, 18)),
        ]);

        $selection = (new LineupHighlightSelector)->select($entries, $items);

        $this->assertSame([2, 3, 4, 5, 6], $selection->slots->keys()->all());
        $this->assertSame([0, 2], $selection->slots->first()['people']->keys()->all());
        $this->assertSame([2], $selection->entries->map->getId()->values()->all());
    }

    public function test_uses_any_slot_with_a_title_or_a_listed_person_when_none_feature_a_highlight(): void
    {
        $entries = new Collection([$this->entry(1, 'HEADLINER'), $this->entry(2, 'STANDARD')]);
        $items = new Collection([
            $this->item(10, null),
            $this->item(11, null, 99),
            $this->item(12, 'Doors'),
            $this->item(13, null, 2),
        ]);

        $selection = (new LineupHighlightSelector)->select($entries, $items);

        $this->assertSame([12, 13], $selection->slots->map(fn (array $slot) => $slot['item']->getId())->values()->all());
        $this->assertSame([1], $selection->entries->map->getId()->values()->all());
    }

    private function entry(int $id, string $tier): EventLineupEntryDomainObject
    {
        return (new EventLineupEntryDomainObject)->setId($id)->setBillingTier($tier)->setIsHidden(false)
            ->setProfile((new ProfileDomainObject)->setId($id)->setName("Act $id"));
    }

    private function item(int $id, ?string $title, int ...$entryIds): EventScheduleItemDomainObject
    {
        return (new EventScheduleItemDomainObject)->setId($id)->setTitle($title)
            ->setPerformers(new Collection(array_map(
                fn (int $entryId) => (new EventScheduleItemPerformerDomainObject)->setEventLineupEntryId($entryId),
                $entryIds,
            )));
    }
}
