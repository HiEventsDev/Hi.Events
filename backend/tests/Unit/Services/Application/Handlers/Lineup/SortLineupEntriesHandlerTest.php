<?php

namespace Tests\Unit\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\SortLineupEntriesHandler;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class SortLineupEntriesHandlerTest extends TestCase
{
    public function test_rejects_ids_from_another_event_before_writing(): void
    {
        $repository = m::mock(EventLineupEntryRepositoryInterface::class);
        $databaseManager = m::mock(DatabaseManager::class);

        $repository->shouldReceive('findWhere')->once()->with(['event_id' => 3], m::any(), m::any())->andReturn(new Collection([
            (new EventLineupEntryDomainObject)->setId(1),
            (new EventLineupEntryDomainObject)->setId(2),
        ]));
        $databaseManager->shouldNotReceive('transaction');
        $repository->shouldNotReceive('updateWhere');

        $this->expectException(ResourceNotFoundException::class);

        (new SortLineupEntriesHandler($repository, $databaseManager))->handle(3, [2, 1, 50]);
    }

    public function test_entries_left_out_of_the_list_keep_their_order_after_the_listed_ones(): void
    {
        $repository = m::mock(EventLineupEntryRepositoryInterface::class);
        $repository->shouldReceive('findWhere')->andReturn(new Collection([
            (new EventLineupEntryDomainObject)->setId(1),
            (new EventLineupEntryDomainObject)->setId(2),
            (new EventLineupEntryDomainObject)->setId(3),
        ]));
        $databaseManager = m::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());

        $positions = [];
        $repository->shouldReceive('updateWhere')->times(3)->andReturnUsing(function (array $attributes, array $where) use (&$positions) {
            $positions[$where['id']] = $attributes['sort_order'];

            return 1;
        });

        (new SortLineupEntriesHandler($repository, $databaseManager))->handle(3, [3]);

        $this->assertSame([3 => 0, 1 => 1, 2 => 2], $positions);
    }
}
