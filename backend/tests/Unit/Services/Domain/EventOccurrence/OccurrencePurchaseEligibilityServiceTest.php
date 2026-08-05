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
            ->andReturn($this->occurrence(EventOccurrenceStatus::ACTIVE->name, capacity: 10, usedCapacity: 10));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('sold out');

        $this->service->assertOccurrencePurchasable(eventId: 1, occurrenceId: 10);
    }

    public function test_rejects_when_capacity_exceeded(): void
    {
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

    public function test_zero_additional_quantity_skips_capacity_check(): void
    {
        $occurrence = $this->occurrence(EventOccurrenceStatus::ACTIVE->name, capacity: 10, usedCapacity: 4);
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->andReturn($occurrence);
        $this->orderItemRepository->shouldNotReceive('getReservedQuantityForOccurrence');

        $result = $this->service->assertOccurrencePurchasable(
            eventId: 1,
            occurrenceId: 10,
            additionalQuantity: 0,
        );

        $this->assertSame($occurrence, $result);
    }

    public function test_zero_additional_quantity_skips_sold_out_gate(): void
    {
        $occurrence = $this->occurrence(EventOccurrenceStatus::ACTIVE->name, capacity: 10, usedCapacity: 10);
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->andReturn($occurrence);
        $this->orderItemRepository->shouldNotReceive('getReservedQuantityForOccurrence');

        $result = $this->service->assertOccurrencePurchasable(
            eventId: 1,
            occurrenceId: 10,
            additionalQuantity: 0,
        );

        $this->assertSame($occurrence, $result);
    }

    public function test_zero_additional_quantity_still_rejects_cancelled_occurrence(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($this->occurrence(EventOccurrenceStatus::CANCELLED->name));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cancelled');

        $this->service->assertOccurrencePurchasable(eventId: 1, occurrenceId: 10, additionalQuantity: 0);
    }

    public function test_zero_additional_quantity_still_rejects_past_occurrence(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($this->occurrence(EventOccurrenceStatus::ACTIVE->name, startDate: '2020-01-01 10:00:00'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already ended');

        $this->service->assertOccurrencePurchasable(eventId: 1, occurrenceId: 10, additionalQuantity: 0);
    }

    public function test_uses_preloaded_occurrence_and_reserved_quantity_without_queries(): void
    {
        $occurrence = $this->occurrence(EventOccurrenceStatus::ACTIVE->name, capacity: 10, usedCapacity: 4);
        $this->occurrenceRepository->shouldNotReceive('findFirstWhere');
        $this->orderItemRepository->shouldNotReceive('getReservedQuantityForOccurrence');

        $result = $this->service->assertOccurrencePurchasable(
            eventId: 1,
            occurrenceId: 10,
            additionalQuantity: 3,
            occurrence: $occurrence,
            reservedQuantity: 3,
        );

        $this->assertSame($occurrence, $result);
    }

    public function test_preloaded_reserved_quantity_counts_against_capacity(): void
    {
        $occurrence = $this->occurrence(EventOccurrenceStatus::ACTIVE->name, capacity: 10, usedCapacity: 4);
        $this->occurrenceRepository->shouldNotReceive('findFirstWhere');
        $this->orderItemRepository->shouldNotReceive('getReservedQuantityForOccurrence');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('capacity');

        $this->service->assertOccurrencePurchasable(
            eventId: 1,
            occurrenceId: 10,
            additionalQuantity: 4,
            occurrence: $occurrence,
            reservedQuantity: 3,
        );
    }

    public function test_preloaded_occurrence_from_other_event_is_rejected(): void
    {
        $occurrence = $this->occurrence(EventOccurrenceStatus::ACTIVE->name)->setEventId(2);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not found');

        $this->service->assertOccurrencePurchasable(
            eventId: 1,
            occurrenceId: 10,
            occurrence: $occurrence,
        );
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
        $occurrence = $this->occurrence(EventOccurrenceStatus::ACTIVE->name, capacity: 10, usedCapacity: 10);
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
        $occurrence = $this->occurrence(EventOccurrenceStatus::ACTIVE->name, capacity: 1, usedCapacity: 5);
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->andReturn($occurrence);

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

        $this->service->assertProductsVisibleOnOccurrence(occurrenceId: 10, productIds: [1, 99]);
    }

    public function test_product_visibility_no_op_for_empty_product_list(): void
    {
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
