<?php

namespace Tests\Unit\Services\Domain\CheckInList;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Domain\CheckInList\CheckInListDataService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckInListDataServiceTest extends TestCase
{
    private CheckInListRepositoryInterface|MockInterface $checkInListRepository;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private CheckInListDataService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkInListRepository = Mockery::mock(CheckInListRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);

        $this->service = new CheckInListDataService(
            $this->checkInListRepository,
            $this->attendeeRepository,
        );
    }

    public function test_verify_attendee_belongs_to_check_in_list_passes_when_product_matches(): void
    {
        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn(1);

        $checkInList = Mockery::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('getProducts')->andReturn(new Collection([$product]));
        $checkInList->shouldReceive('getEventOccurrenceId')->andReturn(null);

        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getProductId')->andReturn(1);

        $this->service->verifyAttendeeBelongsToCheckInList($checkInList, $attendee);

        $this->assertTrue(true);
    }

    public function test_verify_passes_across_occurrences_when_list_has_no_occurrence(): void
    {
        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn(1);

        $checkInList = Mockery::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('getProducts')->andReturn(new Collection([$product]));
        $checkInList->shouldReceive('getEventOccurrenceId')->andReturn(null);

        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getProductId')->andReturn(1);

        $this->service->verifyAttendeeBelongsToCheckInList($checkInList, $attendee);

        $this->assertTrue(true);
    }

    public function test_verify_passes_when_occurrence_matches(): void
    {
        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn(1);

        $checkInList = Mockery::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('getProducts')->andReturn(new Collection([$product]));
        $checkInList->shouldReceive('getEventOccurrenceId')->andReturn(5);

        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getProductId')->andReturn(1);
        $attendee->shouldReceive('getEventOccurrenceId')->andReturn(5);

        $this->service->verifyAttendeeBelongsToCheckInList($checkInList, $attendee);

        $this->assertTrue(true);
    }

    public function test_verify_throws_when_occurrence_mismatch(): void
    {
        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn(1);

        $checkInList = Mockery::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('getProducts')->andReturn(new Collection([$product]));
        $checkInList->shouldReceive('getEventOccurrenceId')->andReturn(5);

        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getProductId')->andReturn(1);
        $attendee->shouldReceive('getEventOccurrenceId')->andReturn(10);
        $attendee->shouldReceive('getFullName')->andReturn('John Doe');

        $this->expectException(CannotCheckInException::class);

        $this->service->verifyAttendeeBelongsToCheckInList($checkInList, $attendee);
    }

    public function test_verify_throws_when_product_mismatch(): void
    {
        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn(1);

        $checkInList = Mockery::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('getProducts')->andReturn(new Collection([$product]));

        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getProductId')->andReturn(99);
        $attendee->shouldReceive('getFullName')->andReturn('Jane Doe');

        $this->expectException(CannotCheckInException::class);

        $this->service->verifyAttendeeBelongsToCheckInList($checkInList, $attendee);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
