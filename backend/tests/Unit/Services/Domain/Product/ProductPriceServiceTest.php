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

    public function test_get_price_uses_override_when_present(): void
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

    public function test_get_price_falls_back_to_base_when_no_override(): void
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

    public function test_get_price_skips_override_lookup_without_occurrence(): void
    {
        $product = $this->createProduct(ProductPriceType::PAID->name, 50.00);
        $orderDetail = new OrderProductPriceDTO(quantity: 1, price_id: 100);

        $this->priceOverrideRepository->shouldNotReceive('findFirstWhere');

        $result = $this->service->getPrice($product, $orderDetail, null);

        $this->assertEquals(50.00, $result->price);
    }

    public function test_get_price_applies_promo_code_after_override(): void
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

    public function test_donation_keeps_donor_amount_above_override_minimum(): void
    {
        $product = $this->createProduct(ProductPriceType::DONATION->name, 10.00);
        $orderDetail = new OrderProductPriceDTO(quantity: 1, price_id: 100, price: 50.00);

        $override = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);
        $override->shouldReceive('getPrice')->andReturn('25.00');

        $this->priceOverrideRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_occurrence_id' => 5,
                'product_price_id' => 100,
            ])
            ->andReturn($override);

        $result = $this->service->getPrice($product, $orderDetail, null, 5);

        $this->assertEquals(50.00, $result->price);
    }

    public function test_donation_enforces_override_as_minimum(): void
    {
        $product = $this->createProduct(ProductPriceType::DONATION->name, 10.00);
        $orderDetail = new OrderProductPriceDTO(quantity: 1, price_id: 100, price: 10.00);

        $override = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);
        $override->shouldReceive('getPrice')->andReturn('25.00');

        $this->priceOverrideRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($override);

        $result = $this->service->getPrice($product, $orderDetail, null, 5);

        $this->assertEquals(25.00, $result->price);
    }

    public function test_donation_without_override_keeps_donor_amount(): void
    {
        $product = $this->createProduct(ProductPriceType::DONATION->name, 10.00);
        $orderDetail = new OrderProductPriceDTO(quantity: 1, price_id: 100, price: 50.00);

        $this->priceOverrideRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(null);

        $result = $this->service->getPrice($product, $orderDetail, null, 5);

        $this->assertEquals(50.00, $result->price);
    }

    public function test_donation_without_override_enforces_product_price_minimum(): void
    {
        $product = $this->createProduct(ProductPriceType::DONATION->name, 10.00);
        $orderDetail = new OrderProductPriceDTO(quantity: 1, price_id: 100, price: 5.00);

        $this->priceOverrideRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(null);

        $result = $this->service->getPrice($product, $orderDetail, null, 5);

        $this->assertEquals(10.00, $result->price);
    }

    public function test_donation_minimum_price_uses_override(): void
    {
        $product = $this->createProduct(ProductPriceType::DONATION->name, 10.00);

        $override = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);
        $override->shouldReceive('getPrice')->andReturn('25.00');

        $this->priceOverrideRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_occurrence_id' => 5,
                'product_price_id' => 100,
            ])
            ->andReturn($override);

        $this->assertEquals(25.00, $this->service->getDonationMinimumPrice($product, 100, 5));
    }

    public function test_donation_minimum_price_falls_back_to_product_price(): void
    {
        $product = $this->createProduct(ProductPriceType::DONATION->name, 10.00);

        $this->priceOverrideRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(null);

        $this->assertEquals(10.00, $this->service->getDonationMinimumPrice($product, 100, 5));
    }

    public function test_donation_minimum_price_without_occurrence_skips_override_lookup(): void
    {
        $product = $this->createProduct(ProductPriceType::DONATION->name, 10.00);

        $this->priceOverrideRepository->shouldNotReceive('findFirstWhere');

        $this->assertEquals(10.00, $this->service->getDonationMinimumPrice($product, 100, null));
    }

    public function test_get_price_returns_free_for_free_product(): void
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
