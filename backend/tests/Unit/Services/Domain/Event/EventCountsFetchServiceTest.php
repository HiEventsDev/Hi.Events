<?php

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Services\Domain\Event\EventCountsFetchService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class EventCountsFetchServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private EventStatisticRepositoryInterface $repository;

    private EventCountsFetchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = m::mock(EventStatisticRepositoryInterface::class);
        $this->service = new EventCountsFetchService($this->repository);
    }

    public function test_it_returns_the_lifetime_counts_for_the_event(): void
    {
        $statistics = (new EventStatisticDomainObject)
            ->setOrdersCreated(12)
            ->setAttendeesRegistered(31);

        $this->repository
            ->shouldReceive('findFirstWhere')
            ->with([EventStatisticDomainObject::EVENT_ID => 5])
            ->once()
            ->andReturn($statistics);

        $counts = $this->service->getEventCounts(5);

        $this->assertSame(12, $counts->total_orders);
        $this->assertSame(31, $counts->total_attendees_registered);
    }

    public function test_it_returns_zeros_when_the_event_has_no_statistics_row(): void
    {
        $this->repository
            ->shouldReceive('findFirstWhere')
            ->with([EventStatisticDomainObject::EVENT_ID => 9])
            ->once()
            ->andReturnNull();

        $counts = $this->service->getEventCounts(9);

        $this->assertSame(0, $counts->total_orders);
        $this->assertSame(0, $counts->total_attendees_registered);
    }
}
