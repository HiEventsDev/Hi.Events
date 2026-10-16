<?php

namespace Tests\Unit\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Repository\Interfaces\ProfileRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\SortProfileImagesHandler;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class SortProfileImagesHandlerTest extends TestCase
{
    public function test_profile_outside_the_organizer_is_not_found(): void
    {
        $profiles = m::mock(ProfileRepositoryInterface::class);
        $images = m::mock(ImageRepositoryInterface::class);
        $profiles->shouldReceive('findFirstWhere')->once()->with([
            'id' => 5,
            'organizer_id' => 2,
            'account_id' => 1,
        ])->andReturnNull();
        $images->shouldNotReceive('updateWhere');

        $this->expectException(ResourceNotFoundException::class);

        (new SortProfileImagesHandler($profiles, $images, m::mock(DatabaseManager::class)))->handle(2, 1, 5, [10]);
    }

    public function test_photos_from_another_profile_are_rejected_before_writing(): void
    {
        $profiles = m::mock(ProfileRepositoryInterface::class);
        $images = m::mock(ImageRepositoryInterface::class);
        $database = m::mock(DatabaseManager::class);
        $profiles->shouldReceive('findFirstWhere')->andReturn((new ProfileDomainObject)->setId(5));
        $images->shouldReceive('findWhere')->once()->with([
            'entity_id' => 5,
            'entity_type' => ProfileDomainObject::class,
            'type' => 'PROFILE_IMAGE',
        ], m::any(), m::any())->andReturn(new Collection([(new ImageDomainObject)->setId(10), (new ImageDomainObject)->setId(11)]));
        $database->shouldNotReceive('transaction');

        $this->expectException(ResourceNotFoundException::class);

        (new SortProfileImagesHandler($profiles, $images, $database))->handle(2, 1, 5, [11, 99]);
    }

    public function test_photos_left_out_of_the_list_keep_their_order_after_the_listed_ones(): void
    {
        $profiles = m::mock(ProfileRepositoryInterface::class);
        $images = m::mock(ImageRepositoryInterface::class);
        $profiles->shouldReceive('findFirstWhere')->andReturn((new ProfileDomainObject)->setId(5));
        $images->shouldReceive('findWhere')->andReturn(new Collection([
            (new ImageDomainObject)->setId(10),
            (new ImageDomainObject)->setId(11),
            (new ImageDomainObject)->setId(12),
        ]));
        $database = m::mock(DatabaseManager::class);
        $database->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());

        $positions = [];
        $images->shouldReceive('updateWhere')->times(3)->andReturnUsing(function (array $attributes, array $where) use (&$positions) {
            $positions[$where['id']] = $attributes['sort_order'];

            return 1;
        });

        (new SortProfileImagesHandler($profiles, $images, $database))->handle(2, 1, 5, [12]);

        $this->assertSame([12 => 0, 10 => 1, 11 => 2], $positions);
    }
}
