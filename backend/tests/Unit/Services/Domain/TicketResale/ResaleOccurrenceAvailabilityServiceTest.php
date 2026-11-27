<?php

namespace Tests\Unit\Services\Domain\TicketResale;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\TicketResale\ResaleOccurrenceAvailabilityService;
use Mockery;
use Tests\TestCase;

class ResaleOccurrenceAvailabilityServiceTest extends TestCase
{
    public function test_a_full_date_with_a_listed_ticket_is_not_reported_as_sold_out(): void
    {
        $repository = Mockery::mock(TicketResaleListingRepositoryInterface::class);
        $repository->shouldReceive('occurrenceIdsWithAvailableListings')->once()->with(10)->andReturn([2]);

        $fullWithoutListing = $this->occurrence(1, capacity: 5, usedCapacity: 5);
        $fullWithListing = $this->occurrence(2, capacity: 5, usedCapacity: 5);
        $open = $this->occurrence(3, capacity: 5, usedCapacity: 1);

        (new ResaleOccurrenceAvailabilityService($repository))
            ->markOccurrencesWithListedTickets(10, collect([$fullWithoutListing, $fullWithListing, $open]));

        $this->assertSame(EventOccurrenceStatus::SOLD_OUT->name, $fullWithoutListing->getStatus());
        $this->assertSame(EventOccurrenceStatus::ACTIVE->name, $fullWithListing->getStatus());
        $this->assertTrue($fullWithListing->isSoldOut());
        $this->assertSame(EventOccurrenceStatus::ACTIVE->name, $open->getStatus());
    }

    public function test_listings_are_not_looked_up_when_no_date_is_full(): void
    {
        $repository = Mockery::mock(TicketResaleListingRepositoryInterface::class);
        $repository->shouldNotReceive('occurrenceIdsWithAvailableListings');

        $occurrences = collect([$this->occurrence(1, capacity: null, usedCapacity: 0)]);

        $this->assertSame(
            $occurrences,
            (new ResaleOccurrenceAvailabilityService($repository))->markOccurrencesWithListedTickets(10, $occurrences),
        );
    }

    private function occurrence(int $id, ?int $capacity, int $usedCapacity): EventOccurrenceDomainObject
    {
        return (new EventOccurrenceDomainObject)
            ->setId($id)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name)
            ->setCapacity($capacity)
            ->setUsedCapacity($usedCapacity);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
