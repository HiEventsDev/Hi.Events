<?php

namespace Tests\Unit\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Domain\Product\ProductPriceService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductPriceServiceTest extends TestCase
{
    private ProductPriceOccurrenceOverrideRepositoryInterface|MockInterface $priceOverrideRepository;
    private ProductPriceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->priceOverrideRepository = Mockery::mock(ProductPriceOccurrenceOverrideRepositoryInterface::class);
        $this->service = new ProductPriceService($this->priceOverrideRepository);
    }

    public function testGetPriceUsesOverrideWhenPresent(): void
    {
        $product = $this->createProduct(ProductPriceType::PAID->name, 50.00);
        $orderDetail = new OrderProductPriceDTO(quantity: 1, price_id: 100);

        $override = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);
        $override->shouldReceive('getPrice')->andReturn('35.00');

        $this->priceOverrideRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_occurrence_id' => 5,
                'product_price_id' => 100,
            ])
            ->andReturn($override);

        $result = $this->service->getPrice($product, $orderDetail, null, 5);

        $this->assertEquals(35.00, $result->price);
    }

    public function testGetPriceFallsBackToBaseWhenNoOverride(): void
    {
        $product = $this->createProduct(ProductPriceType::PAID->name, 50.00);
        $orderDetail = new OrderProductPriceDTO(quantity: 1, price_id: 100);

        $this->priceOverrideRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_occurrence_id' => 5,
                'product_price_id' => 100,
            ])
            ->andReturn(null);

        $result = $this->service->getPrice($product, $orderDetail, null, 5);

        $this->assertEquals(50.00, $result->price);
    }

    public function testGetPriceSkipsOverrideLookupWithoutOccurrence(): void
    {
        $product = $this->createProduct(ProductPriceType::PAID->name, 50.00);
        $orderDetail = new OrderProductPriceDTO(quantity: 1, price_id: 100);

        $this->priceOverrideRepository->shouldNotReceive('findFirstWhere');

        $result = $this->service->getPrice($product, $orderDetail, null);

        $this->assertEquals(50.00, $result->price);
    }

    public function testGetPriceAppliesPromoCodeAfterOverride(): void
    {
        $product = $this->createProduct(ProductPriceType::PAID->name, 50.00);
        $orderDetail = new OrderProductPriceDTO(quantity: 1, price_id: 100);

        $override = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);
        $override->shouldReceive('getPrice')->andReturn('40.00');

        $this->priceOverrideRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($override);

        $promoCode = Mockery::mock(PromoCodeDomainObject::class);
        $promoCode->shouldReceive('appliesToProduct')->andReturn(true);
        $promoCode->shouldReceive('getDiscountType')->andReturn(PromoCodeDiscountTypeEnum::PERCENTAGE->name);
        $promoCode->shouldReceive('isFixedDiscount')->andReturn(false);
        $promoCode->shouldReceive('isPercentageDiscount')->andReturn(true);
        $promoCode->shouldReceive('getDiscount')->andReturn(10);

        $result = $this->service->getPrice($product, $orderDetail, $promoCode, 5);

        $this->assertEquals(36.00, $result->price);
        $this->assertEquals(40.00, $result->price_before_discount);
    }

    public function testGetPriceReturnsFreeForFreeProduct(): void
    {
        $product = $this->createProduct(ProductPriceType::FREE->name, 0.0);
        $orderDetail = new OrderProductPriceDTO(quantity: 1, price_id: 100);

        $this->priceOverrideRepository->shouldReceive('findFirstWhere')->andReturn(null);

        $result = $this->service->getPrice($product, $orderDetail, null, 5);

        $this->assertEquals(0.00, $result->price);
    }

    private function createProduct(string $type, float $price): ProductDomainObject
    {
        $productPrice = Mockery::mock(ProductPriceDomainObject::class);
        $productPrice->shouldReceive('getId')->andReturn(100);
        $productPrice->shouldReceive('getPrice')->andReturn($price);

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getType')->andReturn($type);
        $product->shouldReceive('getPrice')->andReturn($price);
        $product->shouldReceive('getProductPrices')->andReturn(collect([$productPrice]));
        $product->shouldReceive('getPriceById')->with(100)->andReturn($productPrice);

        return $product;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
