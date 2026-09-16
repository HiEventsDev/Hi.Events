<?php

namespace Tests\Unit\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Repository\Eloquent\ProductPriceRepository;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Domain\Product\DTO\ProductPriceDTO;
use HiEvents\Services\Domain\Product\ProductPriceUpdateService;
use HiEvents\Services\Domain\Product\SoldAndReservedQuantitiesService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductPriceUpdateServiceTest extends TestCase
{
    private ProductPriceRepository|MockInterface $productPriceRepository;

    private SoldAndReservedQuantitiesService|MockInterface $soldAndReservedQuantities;

    private ProductPriceUpdateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productPriceRepository = Mockery::mock(ProductPriceRepository::class);
        $this->soldAndReservedQuantities = Mockery::mock(SoldAndReservedQuantitiesService::class);
        $this->service = new ProductPriceUpdateService(
            $this->productPriceRepository,
            $this->soldAndReservedQuantities,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_throws_when_initial_quantity_available_is_less_than_quantity_sold(): void
    {
        $existingPrices = new Collection([$this->createExistingPrice(id: 1, quantitySold: 10, label: 'Early Bird')]);
        [$product, $event] = $this->createProductAndEvent($existingPrices);

        $productsData = $this->createUpsertDTO(ProductPriceType::PAID, [
            new ProductPriceDTO(price: 10.00, initial_quantity_available: 5, id: 1),
        ]);

        $this->expectException(ValidationException::class);
        $this->service->updatePrices($product, $productsData, $existingPrices, $event);
    }

    public function test_allows_initial_quantity_available_equal_to_quantity_sold(): void
    {
        $existingPrices = new Collection([$this->createExistingPrice(id: 1, quantitySold: 10, label: 'Early Bird')]);
        [$product, $event] = $this->createProductAndEvent($existingPrices);

        $this->productPriceRepository->shouldReceive('updateWhere')->once();

        $productsData = $this->createUpsertDTO(ProductPriceType::PAID, [
            new ProductPriceDTO(price: 10.00, initial_quantity_available: 10, id: 1),
        ]);

        $this->service->updatePrices($product, $productsData, $existingPrices, $event);
        $this->assertTrue(true);
    }

    public function test_allows_null_initial_quantity_available(): void
    {
        $existingPrices = new Collection([$this->createExistingPrice(id: 1, quantitySold: 10, label: 'Early Bird')]);
        [$product, $event] = $this->createProductAndEvent($existingPrices);

        $this->productPriceRepository->shouldReceive('updateWhere')->once();

        $productsData = $this->createUpsertDTO(ProductPriceType::PAID, [
            new ProductPriceDTO(price: 10.00, initial_quantity_available: null, id: 1),
        ]);

        $this->service->updatePrices($product, $productsData, $existingPrices, $event);
        $this->assertTrue(true);
    }

    public function test_throws_for_correct_tier_in_tiered_product(): void
    {
        $existingPrices = new Collection([
            $this->createExistingPrice(id: 1, quantitySold: 5, label: 'Tier 1'),
            $this->createExistingPrice(id: 2, quantitySold: 20, label: 'Tier 2'),
        ]);
        [$product, $event] = $this->createProductAndEvent($existingPrices);

        $productsData = $this->createUpsertDTO(ProductPriceType::TIERED, [
            new ProductPriceDTO(price: 10.00, initial_quantity_available: 10, id: 1),
            new ProductPriceDTO(price: 20.00, initial_quantity_available: 15, id: 2),
        ]);

        try {
            $this->service->updatePrices($product, $productsData, $existingPrices, $event);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('prices.1.initial_quantity_available', $errors);
            $this->assertStringContainsString('Tier 2', $errors['prices.1.initial_quantity_available'][0]);
            $this->assertStringContainsString('20', $errors['prices.1.initial_quantity_available'][0]);
        }
    }

    public function test_per_date_tier_on_recurring_event_is_checked_against_max_sold_on_a_single_date(): void
    {
        $existingPrices = new Collection([$this->createExistingPrice(id: 1, quantitySold: 30, label: 'Early Bird')]);
        [$product, $event] = $this->createProductAndEvent($existingPrices, isRecurring: true);

        $this->soldAndReservedQuantities
            ->shouldReceive('getMaxSoldOnAnyOccurrenceByPrice')
            ->once()
            ->with([1], ProductType::TICKET)
            ->andReturn([1 => 8]);
        $this->productPriceRepository->shouldReceive('updateWhere')->once()->with(
            Mockery::on(fn (array $attributes) => $attributes['quantity_applies_to'] === 'OCCURRENCE'
                && $attributes['initial_quantity_available'] === 10),
            ['id' => 1],
        );

        $productsData = $this->createUpsertDTO(ProductPriceType::PAID, [
            new ProductPriceDTO(price: 10.00, initial_quantity_available: 10, id: 1, quantity_applies_to: ProductQuantityAppliesTo::OCCURRENCE),
        ]);

        $this->service->updatePrices($product, $productsData, $existingPrices, $event);
        $this->assertTrue(true);
    }

    public function test_per_date_tier_rejects_quantity_below_max_sold_on_a_single_date(): void
    {
        $existingPrices = new Collection([$this->createExistingPrice(id: 1, quantitySold: 30, label: 'Early Bird')]);
        [$product, $event] = $this->createProductAndEvent($existingPrices, isRecurring: true);

        $this->soldAndReservedQuantities
            ->shouldReceive('getMaxSoldOnAnyOccurrenceByPrice')
            ->once()
            ->andReturn([1 => 12]);

        $productsData = $this->createUpsertDTO(ProductPriceType::PAID, [
            new ProductPriceDTO(price: 10.00, initial_quantity_available: 10, id: 1, quantity_applies_to: ProductQuantityAppliesTo::OCCURRENCE),
        ]);

        try {
            $this->service->updatePrices($product, $productsData, $existingPrices, $event);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('single date', $e->errors()['prices.0.initial_quantity_available'][0]);
            $this->assertStringContainsString('12', $e->errors()['prices.0.initial_quantity_available'][0]);
        }
    }

    public function test_general_product_per_date_tier_uses_order_item_sales(): void
    {
        $existingPrices = new Collection([$this->createExistingPrice(id: 1, quantitySold: 30, label: 'Parking')]);
        [$product, $event] = $this->createProductAndEvent($existingPrices, isRecurring: true);

        $this->soldAndReservedQuantities
            ->shouldReceive('getMaxSoldOnAnyOccurrenceByPrice')
            ->once()
            ->with([1], ProductType::GENERAL)
            ->andReturn([1 => 2]);
        $this->productPriceRepository->shouldReceive('updateWhere')->once();

        $productsData = $this->createUpsertDTO(ProductPriceType::PAID, [
            new ProductPriceDTO(price: 10.00, initial_quantity_available: 5, id: 1, quantity_applies_to: ProductQuantityAppliesTo::OCCURRENCE),
        ], ProductType::GENERAL);

        $this->service->updatePrices($product, $productsData, $existingPrices, $event);
        $this->assertTrue(true);
    }

    public function test_event_wide_tier_on_recurring_event_keeps_the_global_check(): void
    {
        $existingPrices = new Collection([$this->createExistingPrice(id: 1, quantitySold: 30, label: 'Season pass')]);
        [$product, $event] = $this->createProductAndEvent($existingPrices, isRecurring: true);

        $this->soldAndReservedQuantities->shouldNotReceive('getMaxSoldOnAnyOccurrenceByPrice');

        $productsData = $this->createUpsertDTO(ProductPriceType::PAID, [
            new ProductPriceDTO(price: 10.00, initial_quantity_available: 10, id: 1, quantity_applies_to: ProductQuantityAppliesTo::EVENT),
        ]);

        $this->expectException(ValidationException::class);
        $this->service->updatePrices($product, $productsData, $existingPrices, $event);
    }

    public function test_single_event_tiers_are_always_event_wide(): void
    {
        $existingPrices = new Collection([$this->createExistingPrice(id: 1, quantitySold: 0, label: 'Default')]);
        [$product, $event] = $this->createProductAndEvent($existingPrices);

        $this->productPriceRepository->shouldReceive('updateWhere')->once()->with(
            Mockery::on(fn (array $attributes) => $attributes['quantity_applies_to'] === 'EVENT'),
            ['id' => 1],
        );

        $productsData = $this->createUpsertDTO(ProductPriceType::PAID, [
            new ProductPriceDTO(price: 10.00, id: 1, quantity_applies_to: ProductQuantityAppliesTo::OCCURRENCE),
        ]);

        $this->service->updatePrices($product, $productsData, $existingPrices, $event);
        $this->assertTrue(true);
    }

    public function test_missing_quantity_applies_to_defaults_by_product_type(): void
    {
        $existingPrices = new Collection([$this->createExistingPrice(id: 1, quantitySold: 0, label: 'Default')]);
        [$product, $event] = $this->createProductAndEvent($existingPrices);

        $this->productPriceRepository->shouldReceive('updateWhere')->once()->with(
            Mockery::on(fn (array $attributes) => $attributes['quantity_applies_to'] === 'EVENT'),
            ['id' => 1],
        );

        $productsData = $this->createUpsertDTO(ProductPriceType::PAID, [
            new ProductPriceDTO(price: 10.00, id: 1),
        ], ProductType::GENERAL);

        $this->service->updatePrices($product, $productsData, $existingPrices, $event);
        $this->assertTrue(true);
    }

    public function test_missing_quantity_applies_to_on_recurring_ticket_defaults_to_per_date(): void
    {
        $existingPrices = new Collection([$this->createExistingPrice(id: 1, quantitySold: 0, label: 'Default')]);
        [$product, $event] = $this->createProductAndEvent($existingPrices, isRecurring: true);

        $this->productPriceRepository->shouldReceive('updateWhere')->once()->with(
            Mockery::on(fn (array $attributes) => $attributes['quantity_applies_to'] === 'OCCURRENCE'),
            ['id' => 1],
        );

        $productsData = $this->createUpsertDTO(ProductPriceType::PAID, [
            new ProductPriceDTO(price: 10.00, id: 1),
        ]);

        $this->service->updatePrices($product, $productsData, $existingPrices, $event);
        $this->assertTrue(true);
    }

    public function test_rewrites_order_to_match_submitted_tier_sequence(): void
    {
        $existingPrices = new Collection([
            $this->createExistingPrice(id: 1, quantitySold: 0, label: 'Tier 1'),
            $this->createExistingPrice(id: 2, quantitySold: 0, label: 'Tier 2'),
        ]);
        [$product, $event] = $this->createProductAndEvent($existingPrices);

        $orders = [];
        $this->productPriceRepository
            ->shouldReceive('updateWhere')
            ->twice()
            ->andReturnUsing(function (array $attributes, array $where) use (&$orders) {
                $orders[$where['id']] = $attributes['order'];

                return 1;
            });

        $productsData = $this->createUpsertDTO(ProductPriceType::TIERED, [
            new ProductPriceDTO(price: 20.00, label: 'Tier 2', id: 2),
            new ProductPriceDTO(price: 10.00, label: 'Tier 1', id: 1),
        ]);

        $this->service->updatePrices($product, $productsData, $existingPrices, $event);

        $this->assertSame([2 => 1, 1 => 2], $orders);
    }

    private function createExistingPrice(int $id, int $quantitySold, string $label): MockInterface
    {
        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getId')->andReturn($id);
        $price->shouldReceive('getQuantitySold')->andReturn($quantitySold);
        $price->shouldReceive('getLabel')->andReturn($label);

        return $price;
    }

    private function createProductAndEvent(Collection $existingPrices, bool $isRecurring = false): array
    {
        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn(1);
        $product->shouldReceive('getProductPrices')->andReturn($existingPrices);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $event->shouldReceive('isRecurring')->andReturn($isRecurring);

        return [$product, $event];
    }

    private function createUpsertDTO(ProductPriceType $type, array $prices, ProductType $productType = ProductType::TICKET): UpsertProductDTO
    {
        return UpsertProductDTO::fromArray([
            'account_id' => 1,
            'event_id' => 1,
            'product_id' => 1,
            'product_category_id' => 1,
            'title' => 'Test',
            'type' => $type,
            'product_type' => $productType,
            'prices' => new Collection($prices),
        ]);
    }
}
