<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\Order\OrderDiscountAllocationService;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderPlatformFeePassThroughService;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Domain\Product\ProductPriceService;
use HiEvents\Services\Domain\Tax\DTO\TaxCalculationResponse;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Mockery;
use Tests\TestCase;

class OrderItemProcessingServiceTest extends TestCase
{
    private array $capturedOrderItems = [];

    public function test_order_level_discount_is_allocated_and_taxed_on_discounted_prices(): void
    {
        $promoCode = (new PromoCodeDomainObject)
            ->setDiscountType(PromoCodeDiscountTypeEnum::FIXED->name)
            ->setDiscountAppliesTo(PromoCodeDiscountAppliesToEnum::ORDER->name)
            ->setDiscount(30.00);

        $orderItems = $this->processOrder($promoCode);

        $this->assertCount(2, $orderItems);

        [$first, $second] = $this->capturedOrderItems;

        $this->assertEquals(42.50, $first['price']);
        $this->assertEquals(50.00, $first['price_before_discount']);
        $this->assertEquals(85.00, $first['total_before_additions']);

        $this->assertEquals(21.25, $second['price']);
        $this->assertEquals(25.00, $second['price_before_discount']);
        $this->assertEquals(85.00, $second['total_before_additions']);

        $this->assertEquals(8.50, $first['total_tax']);
        $this->assertEquals(8.50, $second['total_tax']);
    }

    public function test_per_product_discount_is_applied_to_every_unit(): void
    {
        $promoCode = (new PromoCodeDomainObject)
            ->setDiscountType(PromoCodeDiscountTypeEnum::FIXED->name)
            ->setDiscountAppliesTo(PromoCodeDiscountAppliesToEnum::EACH_PRODUCT->name)
            ->setDiscount(10.00);

        $this->processOrder($promoCode);

        [$first, $second] = $this->capturedOrderItems;

        $this->assertEquals(40.00, $first['price']);
        $this->assertEquals(50.00, $first['price_before_discount']);
        $this->assertEquals(80.00, $first['total_before_additions']);

        $this->assertEquals(15.00, $second['price']);
        $this->assertEquals(25.00, $second['price_before_discount']);
        $this->assertEquals(60.00, $second['total_before_additions']);
    }

    public function test_indivisible_order_level_discount_splits_a_line_into_exact_items(): void
    {
        $promoCode = (new PromoCodeDomainObject)
            ->setDiscountType(PromoCodeDiscountTypeEnum::FIXED->name)
            ->setDiscountAppliesTo(PromoCodeDiscountAppliesToEnum::ORDER->name)
            ->setDiscount(10.00);

        $orderItems = $this->processOrder($promoCode, [[10, 3]]);

        $this->assertCount(2, $orderItems);

        [$first, $second] = $this->capturedOrderItems;

        $this->assertEquals(46.66, $first['price']);
        $this->assertEquals(1, $first['quantity']);
        $this->assertEquals(50.00, $first['price_before_discount']);
        $this->assertEquals(46.66, $first['total_before_additions']);

        $this->assertEquals(46.67, $second['price']);
        $this->assertEquals(2, $second['quantity']);
        $this->assertEquals(50.00, $second['price_before_discount']);
        $this->assertEquals(93.34, $second['total_before_additions']);

        $this->assertEqualsWithDelta(
            140.00,
            $first['total_before_additions'] + $second['total_before_additions'],
            0.0001,
        );
    }

    public function test_no_promo_code_leaves_prices_untouched(): void
    {
        $this->processOrder(null);

        [$first, $second] = $this->capturedOrderItems;

        $this->assertEquals(50.00, $first['price']);
        $this->assertNull($first['price_before_discount']);
        $this->assertEquals(100.00, $first['total_before_additions']);

        $this->assertEquals(25.00, $second['price']);
        $this->assertNull($second['price_before_discount']);
        $this->assertEquals(100.00, $second['total_before_additions']);
    }

    private function processOrder(?PromoCodeDomainObject $promoCode, array $lines = [[10, 2], [20, 4]])
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setCurrency('USD');

        $order = (new OrderDomainObject)->setId(99);

        $products = [
            10 => $this->createProduct(10, 50.00),
            20 => $this->createProduct(20, 25.00),
        ];

        $orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $orderRepository->shouldReceive('addOrderItem')
            ->andReturnUsing(function (array $data) {
                $this->capturedOrderItems[] = $data;

                return Mockery::mock(OrderItemDomainObject::class);
            });

        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldReceive('loadRelation')->andReturnSelf();
        $productRepository->shouldReceive('findFirstWhere')
            ->andReturnUsing(static fn (array $where) => $products[$where['id']]);

        $taxCalculationService = Mockery::mock(TaxAndFeeCalculationService::class);
        $taxCalculationService->shouldReceive('calculateTaxAndFeesForProduct')
            ->andReturnUsing(static fn ($product, float $price, int $quantity) => new TaxCalculationResponse(
                feeTotal: 0.0,
                taxTotal: round($price * 0.10 * $quantity, 2),
                rollUp: [],
            ));

        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $eventRepository->shouldReceive('findById')->andReturn($event);

        $service = new OrderItemProcessingService(
            orderRepository: $orderRepository,
            productRepository: $productRepository,
            taxCalculationService: $taxCalculationService,
            productPriceService: new ProductPriceService(
                Mockery::mock(ProductPriceOccurrenceOverrideRepositoryInterface::class)
            ),
            platformFeeService: Mockery::mock(OrderPlatformFeePassThroughService::class),
            eventRepository: $eventRepository,
            orderDiscountAllocationService: new OrderDiscountAllocationService,
        );

        return $service->process(
            order: $order,
            productsOrderDetails: collect(array_map(
                static fn (array $line) => new ProductOrderDetailsDTO(
                    product_id: $line[0],
                    quantities: collect([new OrderProductPriceDTO(quantity: $line[1], price_id: $line[0] * 10)]),
                ),
                $lines,
            )),
            event: $event,
            promoCode: $promoCode,
        );
    }

    private function createProduct(int $id, float $price): ProductDomainObject
    {
        return (new ProductDomainObject)
            ->setId($id)
            ->setType(ProductPriceType::PAID->name)
            ->setProductType(ProductType::TICKET->name)
            ->setTitle('Product '.$id)
            ->setProductPrices(collect([
                (new ProductPriceDomainObject)
                    ->setId($id * 10)
                    ->setPrice($price),
            ]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
