<?php

namespace Tests\Unit\Services\Domain\Lineup;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventLocationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemPerformerRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpaceRepositoryInterface;
use HiEvents\Services\Domain\Lineup\EventLineupCloneService;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class EventLineupCloneServiceTest extends TestCase
{
    public function test_copies_entries_spaces_and_slots_shifted_to_the_new_start_date(): void
    {
        $entries = m::mock(EventLineupEntryRepositoryInterface::class);
        $spaces = m::mock(EventSpaceRepositoryInterface::class);
        $items = m::mock(EventScheduleItemRepositoryInterface::class);
        $performers = m::mock(EventScheduleItemPerformerRepositoryInterface::class);
        $locations = m::mock(EventLocationRepositoryInterface::class);
        $occurrences = m::mock(EventOccurrenceRepositoryInterface::class);

        $entries->shouldReceive('findWhere')->with(['event_id' => 1])->andReturn(new Collection([
            (new EventLineupEntryDomainObject)->setId(10)->setProfileId(7)->setBillingTier('HEADLINER')
                ->setRoleLabel('Closing set')->setSortOrder(0)->setIsHidden(false),
        ]));
        $entries->shouldReceive('create')->once()->with(m::subset(['event_id' => 2, 'profile_id' => 7]))
            ->andReturn((new EventLineupEntryDomainObject)->setId(20));

        $spaces->shouldReceive('loadRelation')->andReturnSelf();
        $spaces->shouldReceive('findWhere')->andReturn(new Collection([(new EventSpaceDomainObject)->setId(30)->setName('Main Stage')]));
        $spaces->shouldReceive('create')->once()->with(['event_id' => 2, 'name' => 'Main Stage', 'event_location_id' => null])
            ->andReturn((new EventSpaceDomainObject)->setId(31));

        $occurrences->shouldReceive('findWhere')->with(m::on(fn ($where) => $where === ['event_id' => 1]), m::any(), m::any(), 1)
            ->andReturn(new Collection([(new EventOccurrenceDomainObject)->setStartDate('2030-06-14 19:00:00')]));
        $occurrences->shouldReceive('findWhere')->with(m::on(fn ($where) => $where === ['event_id' => 2]), m::any(), m::any(), 1)
            ->andReturn(new Collection([(new EventOccurrenceDomainObject)->setStartDate('2030-06-21 19:00:00')]));

        $items->shouldReceive('loadRelation')->andReturnSelf();
        $items->shouldReceive('findWhere')->andReturn(new Collection([
            (new EventScheduleItemDomainObject)->setId(40)->setEventSpaceId(30)->setTitle(null)->setDescription(null)
                ->setStartsAt('2030-06-14 21:00:00')->setEndsAt('2030-06-15 00:30:00')
                ->setPerformers(new Collection([(new EventScheduleItemPerformerDomainObject)->setEventLineupEntryId(10)->setSortOrder(0)])),
        ]));
        $items->shouldReceive('create')->once()->with([
            'event_id' => 2,
            'event_space_id' => 31,
            'title' => null,
            'description' => null,
            'starts_at' => '2030-06-21 21:00:00',
            'ends_at' => '2030-06-22 00:30:00',
        ])->andReturn((new EventScheduleItemDomainObject)->setId(41));

        $performers->shouldReceive('insert')->once()->with([[
            'event_schedule_item_id' => 41,
            'event_lineup_entry_id' => 20,
            'sort_order' => 0,
        ]]);

        (new EventLineupCloneService($entries, $spaces, $items, $performers, $locations, $occurrences))
            ->clone(sourceEventId: 1, newEventId: 2, shiftToNewStartDate: true);
    }
}
