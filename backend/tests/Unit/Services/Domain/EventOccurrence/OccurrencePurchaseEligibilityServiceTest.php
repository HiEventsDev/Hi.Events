<?php

namespace Tests\Unit\Services\Domain\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\ProductOccurrenceVisibilityDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OccurrencePurchaseEligibilityServiceTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private OrderItemRepositoryInterface|MockInterface $orderItemRepository;

    private ProductOccurrenceVisibilityRepositoryInterface|MockInterface $visibilityRepository;

    private OccurrencePurchaseEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $this->visibilityRepository = Mockery::mock(ProductOccurrenceVisibilityRepositoryInterface::class);

        $this->orderItemRepository
            ->shouldReceive('getReservedQuantityForOccurrence')
            ->byDefault()
            ->andReturn(0);

        $this->occurrenceRepository
            ->shouldReceive('countWhere')
            ->byDefault()
            ->andReturn(1);

        $this->service = new OccurrencePurchaseEligibilityService(
            $this->occurrenceRepository,
            $this->orderItemRepository,
            $this->visibilityRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rejects_when_occurrence_not_found(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(null);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not found');

        $this->service->assertOccurrencePurchasable(eventId: 1, occurrenceId: 99);
    }

    public function test_rejects_cancelled_occurrence(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($this->occurrence(EventOccurrenceStatus::CANCELLED->name));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cancelled');

        $this->service->assertOccurrencePurchasable(eventId: 1, occurrenceId: 10);
    }

    public function test_rejects_past_occurrence(): void
    {
        // Public payload filters past occurrences out, but a stale client or a
        // direct API caller could still post one — guard belongs at the
        // eligibility chokepoint so public checkout, manual attendee creation
        // and any future caller all inherit it.
        $occurrence = $this->occurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            startDate: '2020-01-01 10:00:00',
        );
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->andReturn($occurrence);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already ended');

        $this->service->assertOccurrencePurchasable(eventId: 1, occurrenceId: 10);
    }

    public function test_rejects_past_occurrence_even_with_capacity_override(): void
    {
        // Organisers using the override flag to manually add an attendee should
        // still not be able to issue tickets for a session that has ended —
        // override only bypasses capacity, not time/status gates.
        $occurrence = $this->occurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            startDate: '2020-01-01 10:00:00',
        );
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->andReturn($occurrence);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already ended');

        $this->service->assertOccurrencePurchasable(
            eventId: 1,
            occurrenceId: 10,
            overrideCapacity: true,
        );
    }

    public function test_rejects_sold_out_occurrence(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($this->occurrence(EventOccurrenceStatus::SOLD_OUT->name));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('sold out');

        $this->service->assertOccurrencePurchasable(eventId: 1, occurrenceId: 10);
    }

    public function test_rejects_when_capacity_exceeded(): void
    {
        // capacity 10, used 4, reserved 3 → available 3; request 5 → reject.
        $occurrence = $this->occurrence(EventOccurrenceStatus::ACTIVE->name, capacity: 10, usedCapacity: 4);
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->andReturn($occurrence);
        $this->orderItemRepository
            ->shouldReceive('getReservedQuantityForOccurrence')
            ->with(10)
            ->andReturn(3);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('capacity');

        $this->service->assertOccurrencePurchasable(eventId: 1, occurrenceId: 10, additionalQuantity: 5);
    }

    public function test_allows_purchase_within_capacity(): void
    {
        $occurrence = $this->occurrence(EventOccurrenceStatus::ACTIVE->name, capacity: 10, usedCapacity: 4);
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->andReturn($occurrence);

        $result = $this->service->assertOccurrencePurchasable(
            eventId: 1,
            occurrenceId: 10,
            additionalQuantity: 3,
        );

        $this->assertSame($occurrence, $result);
    }

    public function test_override_capacity_bypasses_capacity_check_but_not_cancelled(): void
    {
        // Override means capacity is ignored, but cancelled still blocks — there's
        // no point overriding into a cancelled occurrence.
        $occurrence = $this->occurrence(EventOccurrenceStatus::CANCELLED->name, capacity: 1, usedCapacity: 0);
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->andReturn($occurrence);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cancelled');

        $this->service->assertOccurrencePurchasable(
            eventId: 1,
            occurrenceId: 10,
            additionalQuantity: 999,
            overrideCapacity: true,
        );
    }

    public function test_override_capacity_bypasses_sold_out_status(): void
    {
        // SOLD_OUT is a capacity-derived status — ProductQuantityUpdateService
        // flips it once used_capacity hits capacity. The override flag is
        // specifically for the "full occurrence, organiser still wants to add
        // someone" case, so it has to bypass SOLD_OUT too.
        $occurrence = $this->occurrence(EventOccurrenceStatus::SOLD_OUT->name, capacity: 10, usedCapacity: 10);
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->andReturn($occurrence);
        $this->orderItemRepository->shouldNotReceive('getReservedQuantityForOccurrence');

        $result = $this->service->assertOccurrencePurchasable(
            eventId: 1,
            occurrenceId: 10,
            additionalQuantity: 1,
            overrideCapacity: true,
        );

        $this->assertSame($occurrence, $result);
    }

    public function test_override_capacity_allows_exceeding_capacity_for_active_occurrence(): void
    {
        // capacity 1, request 50, with override: should pass.
        $occurrence = $this->occurrence(EventOccurrenceStatus::ACTIVE->name, capacity: 1, usedCapacity: 5);
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->andReturn($occurrence);

        // Capacity check is short-circuited so reserved-quantity lookup must
        // never run — keeps the override path cheap.
        $this->orderItemRepository->shouldNotReceive('getReservedQuantityForOccurrence');

        $result = $this->service->assertOccurrencePurchasable(
            eventId: 1,
            occurrenceId: 10,
            additionalQuantity: 50,
            overrideCapacity: true,
        );

        $this->assertSame($occurrence, $result);
    }

    public function test_uses_single_occurrence_wording_for_single_occurrence_event(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($this->occurrence(EventOccurrenceStatus::CANCELLED->name));
        $this->occurrenceRepository
            ->shouldReceive('countWhere')
            ->andReturn(1);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This event has been cancelled');

        $this->service->assertOccurrencePurchasable(eventId: 1, occurrenceId: 10);
    }

    public function test_uses_occurrence_wording_for_multi_occurrence_event(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($this->occurrence(EventOccurrenceStatus::CANCELLED->name));
        $this->occurrenceRepository
            ->shouldReceive('countWhere')
            ->andReturn(2);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This event occurrence has been cancelled');

        $this->service->assertOccurrencePurchasable(eventId: 1, occurrenceId: 10);
    }

    public function test_product_visibility_allows_all_when_no_rules_exist(): void
    {
        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->andReturn(collect());

        // No exception means all products are allowed (default-visible).
        $this->service->assertProductsVisibleOnOccurrence(occurrenceId: 10, productIds: [1, 2, 3]);
        $this->assertTrue(true);
    }

    public function test_product_visibility_rejects_hidden_product(): void
    {
        $rule = (new ProductOccurrenceVisibilityDomainObject)
            ->setEventOccurrenceId(10)
            ->setProductId(1);

        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->andReturn(collect([$rule]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not available for this occurrence');

        // Product 99 is not in the allow-list.
        $this->service->assertProductsVisibleOnOccurrence(occurrenceId: 10, productIds: [1, 99]);
    }

    public function test_product_visibility_no_op_for_empty_product_list(): void
    {
        // Edge case: empty product list shouldn't even hit the repository.
        $this->visibilityRepository->shouldNotReceive('findWhereIn');

        $this->service->assertProductsVisibleOnOccurrence(occurrenceId: 10, productIds: []);
        $this->assertTrue(true);
    }

    private function occurrence(
        string $status,
        ?int $capacity = null,
        int $usedCapacity = 0,
        string $startDate = '2099-06-15 10:00:00',
    ): EventOccurrenceDomainObject {
        return (new EventOccurrenceDomainObject)
            ->setId(10)
            ->setEventId(1)
            ->setStatus($status)
            ->setCapacity($capacity)
            ->setUsedCapacity($usedCapacity)
            ->setStartDate($startDate);
    }
}
