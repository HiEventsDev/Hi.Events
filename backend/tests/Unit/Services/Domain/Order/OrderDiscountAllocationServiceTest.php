<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Services\Domain\Order\DTO\OrderItemPricingLineDTO;
use HiEvents\Services\Domain\Order\DTO\OrderLineDiscountAllocationDTO;
use HiEvents\Services\Domain\Order\OrderDiscountAllocationService;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Domain\Product\DTO\PriceDTO;
use Tests\TestCase;

class OrderDiscountAllocationServiceTest extends TestCase
{
    private OrderDiscountAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OrderDiscountAllocationService;
    }

    public function test_single_line_single_quantity_gets_exact_discount(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 50.00, quantity: 1),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(10.00), 'USD');

        $this->assertSame([[10.00, 1]], $this->toArrays($result[0]));
    }

    public function test_indivisible_discount_splits_a_line_to_stay_exact(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 20.00, quantity: 3),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(10.00), 'USD');

        $this->assertSame([[3.34, 1], [3.33, 2]], $this->toArrays($result[0]));
        $this->assertEqualsWithDelta(10.00, $this->totalAllocated($result), 0.0001);
    }

    public function test_discount_is_allocated_pro_rata_across_lines(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 50.00, quantity: 2),
            $this->createLine(productId: 2, unitPrice: 25.00, quantity: 4),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(30.00), 'USD');

        $this->assertSame([[7.50, 2]], $this->toArrays($result[0]));
        $this->assertSame([[3.75, 4]], $this->toArrays($result[1]));
    }

    public function test_remainder_is_distributed_exactly_across_lines(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 10.00, quantity: 2),
            $this->createLine(productId: 2, unitPrice: 10.00, quantity: 3),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(9.99), 'USD');

        $this->assertSame([[2.01, 2]], $this->toArrays($result[0]));
        $this->assertSame([[1.99, 3]], $this->toArrays($result[1]));
        $this->assertEqualsWithDelta(9.99, $this->totalAllocated($result), 0.0001);
    }

    public function test_small_discount_on_large_quantity_stays_exact(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 10.00, quantity: 250),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(1.00), 'USD');

        $this->assertSame([[0.01, 100], [0.00, 150]], $this->toArrays($result[0]));
        $this->assertEqualsWithDelta(1.00, $this->totalAllocated($result), 0.0001);
    }

    public function test_exhausted_headroom_falls_back_to_a_split_without_overshooting(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 0.01, quantity: 3),
            $this->createLine(productId: 2, unitPrice: 0.01, quantity: 5),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(0.04), 'USD');

        $this->assertEqualsWithDelta(0.04, $this->totalAllocated($result), 0.0001);
    }

    public function test_zero_decimal_currency_allocates_whole_units(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 1000.0, quantity: 3),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(1000.0), 'JPY');

        $this->assertSame([[334.0, 1], [333.0, 2]], $this->toArrays($result[0]));
        $this->assertEqualsWithDelta(1000.0, $this->totalAllocated($result), 0.0001);
    }

    public function test_discount_larger_than_subtotal_is_clamped_to_subtotal(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 50.00, quantity: 1),
            $this->createLine(productId: 2, unitPrice: 50.00, quantity: 2),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(500.00), 'USD');

        $this->assertSame([[50.00, 1]], $this->toArrays($result[0]));
        $this->assertSame([[50.00, 2]], $this->toArrays($result[1]));
    }

    public function test_discount_equal_to_subtotal_zeroes_every_line(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 20.00, quantity: 2),
            $this->createLine(productId: 2, unitPrice: 10.00, quantity: 1),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(50.00), 'USD');

        $this->assertSame([[20.00, 2]], $this->toArrays($result[0]));
        $this->assertSame([[10.00, 1]], $this->toArrays($result[1]));
    }

    public function test_lines_outside_applicable_products_get_no_discount(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 50.00, quantity: 1),
            $this->createLine(productId: 2, unitPrice: 50.00, quantity: 1),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(10.00, applicableProductIds: [1]), 'USD');

        $this->assertSame([[10.00, 1]], $this->toArrays($result[0]));
        $this->assertSame([[0.00, 1]], $this->toArrays($result[1]));
    }

    public function test_free_and_donation_lines_get_no_discount(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 0.00, quantity: 1, type: ProductPriceType::FREE),
            $this->createLine(productId: 2, unitPrice: 25.00, quantity: 1, type: ProductPriceType::DONATION),
            $this->createLine(productId: 3, unitPrice: 50.00, quantity: 1),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(10.00), 'USD');

        $this->assertSame([[0.00, 1]], $this->toArrays($result[0]));
        $this->assertSame([[0.00, 1]], $this->toArrays($result[1]));
        $this->assertSame([[10.00, 1]], $this->toArrays($result[2]));
    }

    public function test_no_eligible_lines_returns_zero_allocations(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 0.00, quantity: 2, type: ProductPriceType::FREE),
            $this->createLine(productId: 2, unitPrice: 25.00, quantity: 1, type: ProductPriceType::DONATION),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(10.00), 'USD');

        $this->assertSame([[0.00, 2]], $this->toArrays($result[0]));
        $this->assertSame([[0.00, 1]], $this->toArrays($result[1]));
    }

    public function test_allocation_is_deterministic(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 13.33, quantity: 3),
            $this->createLine(productId: 2, unitPrice: 7.77, quantity: 2),
            $this->createLine(productId: 3, unitPrice: 19.99, quantity: 5),
        ]);
        $promoCode = $this->createPromoCode(25.00);

        $this->assertEquals(
            $this->service->allocate($lines, $promoCode, 'USD'),
            $this->service->allocate($lines, $promoCode, 'USD'),
        );
        $this->assertEqualsWithDelta(
            25.00,
            $this->totalAllocated($this->service->allocate($lines, $promoCode, 'USD')),
            0.0001,
        );
    }

    public function test_per_unit_discount_never_exceeds_unit_price(): void
    {
        $lines = collect([
            $this->createLine(productId: 1, unitPrice: 1.00, quantity: 2),
            $this->createLine(productId: 2, unitPrice: 99.99, quantity: 1),
        ]);

        $result = $this->service->allocate($lines, $this->createPromoCode(75.00), 'USD');

        foreach ($result[0] as $allocation) {
            $this->assertLessThanOrEqual(1.00, $allocation->per_unit_discount);
        }
        foreach ($result[1] as $allocation) {
            $this->assertLessThanOrEqual(99.99, $allocation->per_unit_discount);
        }
        $this->assertEqualsWithDelta(75.00, $this->totalAllocated($result), 0.0001);
    }

    /**
     * @param  array<int, array<int, OrderLineDiscountAllocationDTO>>  $allocations
     */
    private function totalAllocated(array $allocations): float
    {
        $total = 0.0;
        foreach ($allocations as $lineAllocations) {
            foreach ($lineAllocations as $allocation) {
                $total += $allocation->per_unit_discount * $allocation->quantity;
            }
        }

        return $total;
    }

    /**
     * @param  array<int, OrderLineDiscountAllocationDTO>  $lineAllocations
     * @return array<int, array{0: float, 1: int}>
     */
    private function toArrays(array $lineAllocations): array
    {
        return array_map(
            static fn (OrderLineDiscountAllocationDTO $allocation) => [$allocation->per_unit_discount, $allocation->quantity],
            $lineAllocations,
        );
    }

    private function createLine(
        int $productId,
        float $unitPrice,
        int $quantity,
        ProductPriceType $type = ProductPriceType::PAID,
    ): OrderItemPricingLineDTO {
        $product = (new ProductDomainObject)
            ->setId($productId)
            ->setType($type->name);

        return new OrderItemPricingLineDTO(
            product: $product,
            product_price: new OrderProductPriceDTO(quantity: $quantity, price_id: $productId * 100),
            prices: new PriceDTO($unitPrice),
            event_occurrence_id: null,
        );
    }

    private function createPromoCode(float $discount, ?array $applicableProductIds = null): PromoCodeDomainObject
    {
        return (new PromoCodeDomainObject)
            ->setDiscountType(PromoCodeDiscountTypeEnum::FIXED->name)
            ->setDiscountAppliesTo(PromoCodeDiscountAppliesToEnum::ORDER->name)
            ->setDiscount($discount)
            ->setApplicableProductIds($applicableProductIds);
    }
}
