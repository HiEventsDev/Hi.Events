<?php

namespace Tests\Unit\Services\Domain\SeatMap;

use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapBandProductRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use Mockery;
use Tests\TestCase;

class SeatedProductLookupServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_an_event_without_a_seat_map_has_no_links(): void
    {
        $lookup = Mockery::mock(EventSeatMapLookupService::class);
        $lookup->shouldReceive('findSummaryForEvent')->with(7)->andReturnNull();
        $bandProducts = Mockery::mock(EventSeatMapBandProductRepositoryInterface::class);
        $bandProducts->shouldNotReceive('findWhere');

        $service = new SeatedProductLookupService($bandProducts, $lookup);

        $this->assertTrue($service->linksForEvent(7)->isEmpty());
        $this->assertSame([], $service->bandKeysByProduct(7));
    }

    public function test_event_links_come_from_the_seat_map_summary(): void
    {
        $links = collect([
            (new EventSeatMapBandProductDomainObject)->setProductId(11)->setBandKey('b_standard'),
            (new EventSeatMapBandProductDomainObject)->setProductId(11)->setBandKey('b_premium'),
        ]);
        $lookup = Mockery::mock(EventSeatMapLookupService::class);
        $lookup->shouldReceive('findSummaryForEvent')->with(7)->andReturn((new EventSeatMapDomainObject)->setId(3)->setEventSeatMapBandProducts($links));
        $bandProducts = Mockery::mock(EventSeatMapBandProductRepositoryInterface::class);
        $bandProducts->shouldNotReceive('findWhere');

        $service = new SeatedProductLookupService($bandProducts, $lookup);

        $this->assertSame($links->all(), $service->linksForEvent(7)->all());
        $this->assertSame([11 => ['b_premium', 'b_standard']], $service->bandKeysByProduct(7));
    }
}
