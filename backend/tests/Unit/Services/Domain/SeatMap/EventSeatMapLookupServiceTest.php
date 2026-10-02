<?php

namespace Tests\Unit\Services\Domain\SeatMap;

use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EventSeatMapLookupServiceTest extends TestCase
{
    private EventSeatMapRepositoryInterface|MockInterface $repository;

    private EventSeatMapLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(EventSeatMapRepositoryInterface::class);
        $this->repository->shouldReceive('loadRelation')->andReturnSelf();
        $this->service = new EventSeatMapLookupService($this->repository, Mockery::mock(SeatMapRepositoryInterface::class));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_an_event_without_a_seat_map_is_looked_up_once_for_every_read(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->once()->andReturnNull();

        $this->assertFalse($this->service->existsForEvent(7));
        $this->assertFalse($this->service->existsForEvent(7));
        $this->assertNull($this->service->findSummaryForEvent(7));
        $this->assertNull($this->service->findForEvent(7));
        $this->assertNull($this->service->bandOf(7, 'e1.0.0'));
    }

    public function test_forget_drops_a_remembered_absence(): void
    {
        $eventSeatMap = (new EventSeatMapDomainObject)->setId(3)->setEventId(7);
        $this->repository->shouldReceive('findFirstWhere')->twice()->andReturn(null, $eventSeatMap);

        $this->assertFalse($this->service->existsForEvent(7));

        $this->service->forget(7);

        $this->assertTrue($this->service->existsForEvent(7));
    }

    public function test_the_summary_reuses_a_seat_map_already_loaded_in_full(): void
    {
        $eventSeatMap = (new EventSeatMapDomainObject)->setId(3)->setEventId(7)
            ->setEventSeatMapBandProducts(collect([(new EventSeatMapBandProductDomainObject)->setProductId(11)->setBandKey('b_standard')]));
        $this->repository->shouldReceive('findFirstWhere')->once()->andReturn($eventSeatMap);

        $this->assertSame($eventSeatMap, $this->service->findForEvent(7));
        $this->assertSame($eventSeatMap, $this->service->findSummaryForEvent(7));
        $this->assertTrue($this->service->existsForEvent(7));
    }

    public function test_the_full_seat_map_is_loaded_even_when_a_summary_is_remembered(): void
    {
        $summary = (new EventSeatMapDomainObject)->setId(3)->setEventId(7);
        $full = (new EventSeatMapDomainObject)->setId(3)->setEventId(7)->setLayout(['elements' => []]);
        $this->repository->shouldReceive('findFirstWhere')->twice()->andReturn($summary, $full);

        $this->assertTrue($this->service->existsForEvent(7));
        $this->assertSame($full, $this->service->findForEvent(7));
        $this->assertSame($full, $this->service->findSummaryForEvent(7));
    }
}
