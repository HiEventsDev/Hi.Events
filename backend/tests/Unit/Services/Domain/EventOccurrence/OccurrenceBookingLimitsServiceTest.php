<?php

namespace Tests\Unit\Services\Domain\EventOccurrence;

use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\EventOccurrence\OccurrenceBookingLimitsService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OccurrenceBookingLimitsServiceTest extends TestCase
{
    private ProductPriceOccurrenceOverrideRepositoryInterface|MockInterface $overrideRepository;

    private ProductRepositoryInterface|MockInterface $productRepository;

    private OccurrenceBookingLimitsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->overrideRepository = Mockery::mock(ProductPriceOccurrenceOverrideRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->productRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->service = new OccurrenceBookingLimitsService($this->overrideRepository, $this->productRepository);
    }

    private function limitsFor(EventOccurrenceDomainObject $occurrence, $products)
    {
        $this->productRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with(
                ['event_id' => 1],
                ['*'],
                Mockery::on(fn (array $orders) => array_map(fn (OrderAndDirection $o) => $o->getOrder(), $orders) === ['order', 'id']),
            )
            ->andReturn($products);
        $this->service->attachTo(collect([$occurrence]), 1);

        return $occurrence->getBookingLimits();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sellable_is_the_lower_of_capacity_and_per_date_allocations(): void
    {
        $this->overrideRepository->shouldReceive('findWhereIn')->once()->andReturn(collect());
        $occurrence = $this->occurrence(1, capacity: 22);
        $products = collect([
            $this->product('Workshop', ProductType::TICKET, [
                [11, 'Early bird', 5, ProductQuantityAppliesTo::OCCURRENCE],
                [12, 'Standard', 15, ProductQuantityAppliesTo::OCCURRENCE],
            ]),
            $this->product('T-shirt', ProductType::GENERAL, [[13, null, 3, ProductQuantityAppliesTo::OCCURRENCE]]),
        ]);

        $limits = $this->limitsFor($occurrence, $products);

        $this->assertSame(22, $limits->capacity);
        $this->assertSame(20, $limits->allocation_total);
        $this->assertSame(20, $limits->sellable);
        $this->assertSame([11, 12], array_map(fn ($a) => $a->product_price_id, $limits->allocations));
        $this->assertSame([5, 15], array_map(fn ($a) => $a->quantity, $limits->allocations));
    }

    public function test_capacity_wins_when_allocations_exceed_it_and_overrides_replace_tier_caps(): void
    {
        $override = (new ProductPriceOccurrenceOverrideDomainObject)->setEventOccurrenceId(1)->setProductPriceId(11)->setQuantityAvailable(30);
        $this->overrideRepository->shouldReceive('findWhereIn')->once()->with('event_occurrence_id', [1])->andReturn(collect([$override]));
        $occurrence = $this->occurrence(1, capacity: 22);
        $products = collect([
            $this->product('Class', ProductType::TICKET, [[11, null, 5, ProductQuantityAppliesTo::OCCURRENCE]]),
        ]);

        $limits = $this->limitsFor($occurrence, $products);

        $this->assertSame(30, $limits->allocation_total);
        $this->assertSame(22, $limits->sellable);
        $this->assertSame(30, $limits->allocations[0]->quantity);
    }

    public function test_an_unlimited_or_event_wide_tier_leaves_only_capacity_as_the_limit(): void
    {
        $this->overrideRepository->shouldReceive('findWhereIn')->once()->andReturn(collect());
        $products = collect([
            $this->product('Entry', ProductType::TICKET, [
                [11, 'Adult', null, ProductQuantityAppliesTo::OCCURRENCE],
                [12, 'Season pass', 200, ProductQuantityAppliesTo::EVENT],
            ]),
        ]);

        $withCapacity = $this->limitsFor($this->occurrence(1, capacity: 100), $products);
        $this->assertNull($withCapacity->allocation_total);
        $this->assertSame(100, $withCapacity->sellable);
        $this->assertSame([[11, null, 'OCCURRENCE'], [12, null, 'EVENT']], array_map(fn ($a) => [$a->product_price_id, $a->quantity, $a->applies_to], $withCapacity->allocations));

        $this->overrideRepository->shouldReceive('findWhereIn')->once()->andReturn(collect());
        $noLimits = $this->limitsFor($this->occurrence(2, capacity: null), $products);
        $this->assertNull($noLimits->sellable);
    }

    public function test_a_capped_tier_is_still_listed_when_another_tier_is_unlimited(): void
    {
        $this->overrideRepository->shouldReceive('findWhereIn')->once()->andReturn(collect());
        $products = collect([
            $this->product('VIP', ProductType::TICKET, [[11, null, 50, ProductQuantityAppliesTo::OCCURRENCE]]),
            $this->product('Standard', ProductType::TICKET, [[12, null, null, ProductQuantityAppliesTo::EVENT]]),
        ]);

        $limits = $this->limitsFor($this->occurrence(1, capacity: null), $products);

        $this->assertNull($limits->allocation_total);
        $this->assertNull($limits->sellable);
        $this->assertSame([50, null], array_map(fn ($a) => $a->quantity, $limits->allocations));
    }

    public function test_tier_allocations_alone_define_the_sellable_total_without_a_capacity(): void
    {
        $this->overrideRepository->shouldReceive('findWhereIn')->once()->andReturn(collect());
        $products = collect([
            $this->product('Theatre', ProductType::TICKET, [
                [11, 'Stalls', 100, ProductQuantityAppliesTo::OCCURRENCE],
                [12, 'Circle', 50, ProductQuantityAppliesTo::OCCURRENCE],
                [13, 'Balcony', 30, ProductQuantityAppliesTo::OCCURRENCE],
            ]),
        ]);

        $limits = $this->limitsFor($this->occurrence(1, capacity: null), $products);

        $this->assertSame(180, $limits->sellable);
        $this->assertNull($limits->capacity);
    }

    public function test_tiers_are_listed_in_their_configured_order(): void
    {
        $this->overrideRepository->shouldReceive('findWhereIn')->once()->andReturn(collect());
        $products = collect([
            $this->product('Theatre', ProductType::TICKET, [
                [12, 'Circle', 50, ProductQuantityAppliesTo::OCCURRENCE, 2],
                [11, 'Stalls', 100, ProductQuantityAppliesTo::OCCURRENCE, 1],
            ]),
        ]);

        $limits = $this->limitsFor($this->occurrence(1, capacity: null), $products);

        $this->assertSame(['Stalls', 'Circle'], array_map(fn ($allocation) => $allocation->price_label, $limits->allocations));
    }

    private function occurrence(int $id, ?int $capacity): EventOccurrenceDomainObject
    {
        return (new EventOccurrenceDomainObject)->setId($id)->setCapacity($capacity);
    }

    private function product(string $title, ProductType $type, array $prices): ProductDomainObject
    {
        return (new ProductDomainObject)
            ->setTitle($title)
            ->setProductType($type->name)
            ->setProductPrices(collect(array_map(fn (array $p) => (new ProductPriceDomainObject)
                ->setId($p[0])
                ->setLabel($p[1])
                ->setInitialQuantityAvailable($p[2])
                ->setQuantityAppliesTo($p[3]->name)
                ->setOrder($p[4] ?? 1), $prices)));
    }
}
