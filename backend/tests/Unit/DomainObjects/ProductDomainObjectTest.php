<?php

namespace Tests\Unit\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use Tests\TestCase;

class ProductDomainObjectTest extends TestCase
{
    public function test_locks_nothing_when_sequential_release_is_off(): void
    {
        $product = $this->createTieredProduct(sequential: false, prices: [
            $this->createPrice(id: 1, order: 1, initialQuantity: 10, sold: 0),
            $this->createPrice(id: 2, order: 2, initialQuantity: 10, sold: 0),
        ]);

        $product->markLockedTiers();

        $this->assertSame([false, false], $this->lockedFlags($product));
    }

    public function test_locks_nothing_for_non_tiered_products(): void
    {
        $product = $this->createTieredProduct(sequential: true, prices: [
            $this->createPrice(id: 1, order: 1, initialQuantity: 10, sold: 0),
            $this->createPrice(id: 2, order: 2, initialQuantity: 10, sold: 0),
        ])->setType(ProductPriceType::PAID->name);

        $product->markLockedTiers();

        $this->assertSame([false, false], $this->lockedFlags($product));
    }

    public function test_locks_later_tiers_while_first_tier_has_stock(): void
    {
        $product = $this->createTieredProduct(sequential: true, prices: [
            $this->createPrice(id: 1, order: 1, initialQuantity: 10, sold: 3),
            $this->createPrice(id: 2, order: 2, initialQuantity: 10, sold: 0),
            $this->createPrice(id: 3, order: 3, initialQuantity: null, sold: 0),
        ]);

        $product->markLockedTiers();

        $this->assertSame([false, true, true], $this->lockedFlags($product));
    }

    public function test_unlocks_next_tier_when_first_tier_sold_out(): void
    {
        $product = $this->createTieredProduct(sequential: true, prices: [
            $this->createPrice(id: 1, order: 1, initialQuantity: 10, sold: 10),
            $this->createPrice(id: 2, order: 2, initialQuantity: 10, sold: 0),
            $this->createPrice(id: 3, order: 3, initialQuantity: null, sold: 0),
        ]);

        $product->markLockedTiers();

        $this->assertSame([false, false, true], $this->lockedFlags($product));
    }

    public function test_reservations_count_towards_exhausting_a_tier(): void
    {
        $product = $this->createTieredProduct(sequential: true, prices: [
            $this->createPrice(id: 1, order: 1, initialQuantity: 10, sold: 7)->setQuantityReserved(3),
            $this->createPrice(id: 2, order: 2, initialQuantity: 10, sold: 0),
        ]);

        $product->markLockedTiers();

        $this->assertSame([false, false], $this->lockedFlags($product));
    }

    public function test_per_date_tier_is_exhausted_from_resolved_availability_not_event_wide_sales(): void
    {
        $product = $this->createTieredProduct(sequential: true, prices: [
            $this->createPrice(id: 1, order: 1, initialQuantity: 10, sold: 40)
                ->setQuantityAppliesTo(ProductQuantityAppliesTo::OCCURRENCE->name)
                ->setQuantityAvailable(4),
            $this->createPrice(id: 2, order: 2, initialQuantity: 10, sold: 0),
        ]);

        $product->markLockedTiers();

        $this->assertSame([false, true], $this->lockedFlags($product));
    }

    public function test_per_date_tier_with_no_availability_on_this_date_unlocks_next_tier(): void
    {
        $product = $this->createTieredProduct(sequential: true, prices: [
            $this->createPrice(id: 1, order: 1, initialQuantity: 10, sold: 2)
                ->setQuantityAppliesTo(ProductQuantityAppliesTo::OCCURRENCE->name)
                ->setQuantityAvailable(0),
            $this->createPrice(id: 2, order: 2, initialQuantity: 10, sold: 0),
        ]);

        $product->markLockedTiers();

        $this->assertSame([false, false], $this->lockedFlags($product));
    }

    public function test_tier_past_its_sale_end_date_counts_as_exhausted(): void
    {
        $product = $this->createTieredProduct(sequential: true, prices: [
            $this->createPrice(id: 1, order: 1, initialQuantity: 10, sold: 0)
                ->setSaleEndDate(Carbon::now()->subDay()->toDateTimeString()),
            $this->createPrice(id: 2, order: 2, initialQuantity: 10, sold: 0),
        ]);

        $product->markLockedTiers();

        $this->assertSame([false, false], $this->lockedFlags($product));
    }

    public function test_hidden_tiers_neither_gate_nor_get_locked(): void
    {
        $product = $this->createTieredProduct(sequential: true, prices: [
            $this->createPrice(id: 1, order: 1, initialQuantity: 10, sold: 0)->setIsHidden(true),
            $this->createPrice(id: 2, order: 2, initialQuantity: 10, sold: 10),
            $this->createPrice(id: 3, order: 3, initialQuantity: 10, sold: 0)->setIsHidden(true),
            $this->createPrice(id: 4, order: 4, initialQuantity: 10, sold: 0),
        ]);

        $product->markLockedTiers();

        $this->assertSame([false, false, false, false], $this->lockedFlags($product));
    }

    public function test_unlimited_earlier_tier_keeps_later_tiers_locked(): void
    {
        $product = $this->createTieredProduct(sequential: true, prices: [
            $this->createPrice(id: 1, order: 1, initialQuantity: null, sold: 500),
            $this->createPrice(id: 2, order: 2, initialQuantity: 10, sold: 0),
        ]);

        $product->markLockedTiers();

        $this->assertSame([false, true], $this->lockedFlags($product));
    }

    public function test_uses_order_column_rather_than_collection_position(): void
    {
        $product = $this->createTieredProduct(sequential: true, prices: [
            $this->createPrice(id: 2, order: 2, initialQuantity: 10, sold: 0),
            $this->createPrice(id: 1, order: 1, initialQuantity: 10, sold: 0),
        ]);

        $product->markLockedTiers();

        $this->assertTrue($product->getPriceById(2)->isLockedBehindEarlierTier());
        $this->assertFalse($product->getPriceById(1)->isLockedBehindEarlierTier());
    }

    public function test_quantity_available_excludes_locked_tiers(): void
    {
        $product = $this->createTieredProduct(sequential: true, prices: [
            $this->createPrice(id: 1, order: 1, initialQuantity: 10, sold: 3)->setQuantityAvailable(7),
            $this->createPrice(id: 2, order: 2, initialQuantity: 100, sold: 0)->setQuantityAvailable(100),
        ]);

        $product->markLockedTiers();

        $this->assertSame(7, $product->getQuantityAvailable());
    }

    private function createTieredProduct(bool $sequential, array $prices): ProductDomainObject
    {
        return (new ProductDomainObject)
            ->setId(1)
            ->setType(ProductPriceType::TIERED->name)
            ->setSequentialTierReleaseEnabled($sequential)
            ->setProductPrices(collect($prices));
    }

    private function createPrice(int $id, int $order, ?int $initialQuantity, int $sold): ProductPriceDomainObject
    {
        return (new ProductPriceDomainObject)
            ->setId($id)
            ->setOrder($order)
            ->setPrice(10.00)
            ->setInitialQuantityAvailable($initialQuantity)
            ->setQuantityAppliesTo(ProductQuantityAppliesTo::EVENT->name)
            ->setQuantitySold($sold);
    }

    private function lockedFlags(ProductDomainObject $product): array
    {
        return $product->getProductPrices()
            ->map(fn (ProductPriceDomainObject $price) => $price->isLockedBehindEarlierTier())
            ->values()
            ->all();
    }
}
