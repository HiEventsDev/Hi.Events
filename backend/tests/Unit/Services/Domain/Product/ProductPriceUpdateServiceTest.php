<?php

namespace Tests\Unit\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Repository\Eloquent\ProductPriceRepository;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Domain\Product\DTO\ProductPriceDTO;
use HiEvents\Services\Domain\Product\ProductPriceUpdateService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductPriceUpdateServiceTest extends TestCase
{
    private ProductPriceRepository|MockInterface $productPriceRepository;
    private ProductPriceUpdateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productPriceRepository = Mockery::mock(ProductPriceRepository::class);
        $this->service = new ProductPriceUpdateService($this->productPriceRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testThrowsWhenInitialQuantityAvailableIsLessThanQuantitySold(): void
    {
        $existingPrices = new Collection([$this->createExistingPrice(id: 1, quantitySold: 10, label: 'Early Bird')]);
        [$product, $event] = $this->createProductAndEvent($existingPrices);

        $productsData = $this->createUpsertDTO(ProductPriceType::PAID, [
            new ProductPriceDTO(price: 10.00, initial_quantity_available: 5, id: 1),
        ]);

        $this->expectException(ValidationException::class);
        $this->service->updatePrices($product, $productsData, $existingPrices, $event);
    }

    public function testAllowsInitialQuantityAvailableEqualToQuantitySold(): void
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

    public function testAllowsNullInitialQuantityAvailable(): void
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

    public function testThrowsForCorrectTierInTieredProduct(): void
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

    private function createExistingPrice(int $id, int $quantitySold, string $label): MockInterface
    {
        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getId')->andReturn($id);
        $price->shouldReceive('getQuantitySold')->andReturn($quantitySold);
        $price->shouldReceive('getLabel')->andReturn($label);
        return $price;
    }

    private function createProductAndEvent(Collection $existingPrices): array
    {
        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn(1);
        $product->shouldReceive('getProductPrices')->andReturn($existingPrices);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('UTC');

        return [$product, $event];
    }

    private function createUpsertDTO(ProductPriceType $type, array $prices): UpsertProductDTO
    {
        return UpsertProductDTO::fromArray([
            'account_id' => 1,
            'event_id' => 1,
            'product_id' => 1,
            'product_category_id' => 1,
            'title' => 'Test',
            'type' => $type,
            'product_type' => ProductType::TICKET,
            'prices' => new Collection($prices),
        ]);
    }
}
