<?php

namespace Tests\Unit\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Services\Domain\Product\SoldAndReservedQuantitiesService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SoldAndReservedQuantitiesServiceTest extends TestCase
{
    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private OrderItemRepositoryInterface|MockInterface $orderItemRepository;

    private SoldAndReservedQuantitiesService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $this->service = new SoldAndReservedQuantitiesService($this->attendeeRepository, $this->orderItemRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ticket_sales_come_from_attendees_and_general_sales_from_order_items(): void
    {
        $this->attendeeRepository->shouldReceive('getSoldQuantitiesByPriceForOccurrence')->once()->with(5)->andReturn([1 => 2]);
        $this->orderItemRepository->shouldReceive('getSoldQuantitiesByPriceForOccurrence')->once()->with(5)->andReturn([2 => 3]);

        $this->assertSame([1 => 2], $this->service->getSoldByPriceForOccurrence(5, ProductType::TICKET));
        $this->assertSame([2 => 3], $this->service->getSoldByPriceForOccurrence(5, ProductType::GENERAL));
    }

    public function test_busiest_date_lookup_follows_the_same_split(): void
    {
        $this->attendeeRepository->shouldReceive('getMaxSoldPerOccurrenceByPrice')->once()->with([1])->andReturn([1 => 9]);
        $this->orderItemRepository->shouldReceive('getMaxSoldPerOccurrenceByPrice')->once()->with([2])->andReturn([2 => 4]);

        $this->assertSame([1 => 9], $this->service->getMaxSoldOnAnyOccurrenceByPrice([1], ProductType::TICKET));
        $this->assertSame([2 => 4], $this->service->getMaxSoldOnAnyOccurrenceByPrice([2], ProductType::GENERAL));
    }

    public function test_reservations_are_read_from_order_items(): void
    {
        $this->orderItemRepository->shouldReceive('getReservedQuantitiesByPrice')->once()->with(7, null)->andReturn([1 => 1]);
        $this->orderItemRepository->shouldReceive('getReservedQuantitiesByPrice')->once()->with(7, 5)->andReturn([1 => 0]);
        $this->orderItemRepository->shouldReceive('getReservedTicketQuantityForOccurrence')->once()->with(5)->andReturn(6);

        $this->assertSame([1 => 1], $this->service->getReservedByPrice(7));
        $this->assertSame([1 => 0], $this->service->getReservedByPrice(7, 5));
        $this->assertSame(6, $this->service->getReservedTicketsForOccurrence(5));
    }
}
