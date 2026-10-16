<?php

namespace Tests\Unit\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventLocationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpaceRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\GetEventLineupHandler;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class GetEventLineupHandlerTest extends TestCase
{
    public function test_public_lineup_hides_hidden_entries_and_their_untitled_slots(): void
    {
        $visible = $this->entry(1, hidden: false);
        $hidden = $this->entry(2, hidden: true);
        $orphaned = $this->entry(3, hidden: false)->setProfile(null);

        $titledSlotWithHiddenGuest = (new EventScheduleItemDomainObject)->setId(10)->setTitle('Panel')
            ->setPerformers(new Collection([$this->performer(1), $this->performer(2)]));
        $untitledHiddenSlot = (new EventScheduleItemDomainObject)->setId(11)->setTitle(null)
            ->setPerformers(new Collection([$this->performer(2)]));

        $lineupEntries = m::mock(EventLineupEntryRepositoryInterface::class);
        $lineupEntries->shouldReceive('loadRelation')->andReturnSelf();
        $lineupEntries->shouldReceive('findWhere')->andReturn(new Collection([$visible, $hidden, $orphaned]));

        $spaces = m::mock(EventSpaceRepositoryInterface::class);
        $spaces->shouldReceive('loadRelation')->andReturnSelf();
        $spaces->shouldReceive('findWhere')->andReturn(new Collection);

        $items = m::mock(EventScheduleItemRepositoryInterface::class);
        $items->shouldReceive('loadRelation')->andReturnSelf();
        $items->shouldReceive('findWhere')->andReturn(new Collection([$titledSlotWithHiddenGuest, $untitledHiddenSlot]));

        $venues = m::mock(EventLocationRepositoryInterface::class);
        $venues->shouldNotReceive('findWhere');

        $settings = m::mock(EventSettingsRepositoryInterface::class);
        $settings->shouldReceive('findFirstWhere')->andReturn(null);

        $lineup = (new GetEventLineupHandler($lineupEntries, $spaces, $items, $venues, $settings))->handle(5, forPublic: true);

        $this->assertSame([1], $lineup->entries->map->getId()->all());
        $this->assertSame([10], $lineup->schedule_items->map->getId()->all());
        $this->assertSame(
            [1],
            $lineup->schedule_items->first()->getPerformers()->map->getEventLineupEntryId()->all(),
        );
        $this->assertNull($lineup->venues);
    }

    public function test_public_lineup_follows_the_homepage_section_toggles(): void
    {
        $scheduled = $this->entry(1, hidden: false);
        $unscheduled = $this->entry(2, hidden: false);
        $slot = (new EventScheduleItemDomainObject)->setId(10)->setTitle(null)
            ->setPerformers(new Collection([$this->performer(1)]));

        $handler = function (?array $sections) use ($scheduled, $unscheduled, $slot) {
            $lineupEntries = m::mock(EventLineupEntryRepositoryInterface::class);
            $lineupEntries->shouldReceive('loadRelation')->andReturnSelf();
            $lineupEntries->shouldReceive('findWhere')->andReturn(new Collection([$scheduled, $unscheduled]));
            $spaces = m::mock(EventSpaceRepositoryInterface::class);
            $spaces->shouldReceive('loadRelation')->andReturnSelf();
            $spaces->shouldReceive('findWhere')->andReturn(new Collection([(new EventSpaceDomainObject)->setId(3)]));
            $items = m::mock(EventScheduleItemRepositoryInterface::class);
            $items->shouldReceive('loadRelation')->andReturnSelf();
            $items->shouldReceive('findWhere')->andReturn(new Collection([clone $slot]));
            $settings = m::mock(EventSettingsRepositoryInterface::class);
            $settings->shouldReceive('findFirstWhere')->andReturn((new EventSettingDomainObject)->setHomepageSections($sections));

            return (new GetEventLineupHandler($lineupEntries, $spaces, $items, m::mock(EventLocationRepositoryInterface::class), $settings))
                ->handle(5, forPublic: true);
        };

        $scheduleOff = $handler([['id' => 'schedule', 'type' => 'SCHEDULE', 'enabled' => false]]);
        $this->assertSame([1, 2], $scheduleOff->entries->map->getId()->all());
        $this->assertCount(0, $scheduleOff->schedule_items);
        $this->assertCount(0, $scheduleOff->spaces);

        $lineupOff = $handler([['id' => 'lineup', 'type' => 'LINEUP', 'enabled' => false]]);
        $this->assertSame([1], $lineupOff->entries->map->getId()->all());
        $this->assertCount(1, $lineupOff->schedule_items);

        $bothOff = $handler([
            ['id' => 'lineup', 'type' => 'LINEUP', 'enabled' => false],
            ['id' => 'schedule', 'type' => 'SCHEDULE', 'enabled' => false],
        ]);
        $this->assertCount(0, $bothOff->entries);
        $this->assertCount(0, $bothOff->schedule_items);
    }

    private function entry(int $id, bool $hidden): EventLineupEntryDomainObject
    {
        return (new EventLineupEntryDomainObject)
            ->setId($id)
            ->setIsHidden($hidden)
            ->setProfile((new ProfileDomainObject)->setId($id));
    }

    private function performer(int $entryId): EventScheduleItemPerformerDomainObject
    {
        return (new EventScheduleItemPerformerDomainObject)->setEventLineupEntryId($entryId);
    }
}
