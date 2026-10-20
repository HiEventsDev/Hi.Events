<?php

namespace Tests\Unit\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Enums\TaxCalculationType;
use HiEvents\DomainObjects\Enums\TaxType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\Order\OrderDiscountAllocationService;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderPlatformFeePassThroughService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Domain\Product\ProductFilterService;
use HiEvents\Services\Domain\Product\ProductPriceService;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use HiEvents\Services\Domain\Tax\TaxAndFeeRollupService;
use HiEvents\Services\Infrastructure\CurrencyConversion\CurrencyConversionClientInterface;
use Illuminate\Config\Repository;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductFilterServiceDisplayPricingTest extends TestCase
{
    private const EVENT_ID = 10;

    private const OCCURRENCE_ID = 7;

    private array $overridePrices = [];

    private array $capturedOrderItems = [];

    public function test_a_promo_on_a_per_date_override_strikes_the_override_price(): void
    {
        $this->overridePrices = [100 => 45.00];
        $product = $this->createPaidProduct(price: 30.00);

        $this->filter($product, $this->percentagePromo(10), self::OCCURRENCE_ID);

        $price = $product->getProductPrices()->first();
        $this->assertSame(40.50, $price->getPrice());
        $this->assertSame(45.00, $price->getPriceBeforeDiscount());
    }

    public function test_a_promo_without_an_override_strikes_the_base_price(): void
    {
        $product = $this->createPaidProduct(price: 30.00);

        $this->filter($product, $this->percentagePromo(10), self::OCCURRENCE_ID);

        $price = $product->getProductPrices()->first();
        $this->assertSame(27.00, $price->getPrice());
        $this->assertSame(30.00, $price->getPriceBeforeDiscount());
    }

    public function test_a_promo_on_a_tiered_product_strikes_each_tiers_own_date_price(): void
    {
        $this->overridePrices = [101 => 60.00];
        $product = (new ProductDomainObject)
            ->setId(1)
            ->setEventId(self::EVENT_ID)
            ->setType(ProductPriceType::TIERED->name)
            ->setProductType(ProductType::TICKET->name)
            ->setTitle('Ticket')
            ->setProductPrices(collect([
                (new ProductPriceDomainObject)->setId(100)->setOrder(1)->setLabel('Early')->setPrice(20.00),
                (new ProductPriceDomainObject)->setId(101)->setOrder(2)->setLabel('Late')->setPrice(25.00),
            ]));

        $this->filter($product, $this->fixedPromo(5.00), self::OCCURRENCE_ID);

        [$early, $late] = $product->getProductPrices()->values()->all();
        $this->assertSame(15.00, $early->getPrice());
        $this->assertSame(20.00, $early->getPriceBeforeDiscount());
        $this->assertSame(55.00, $late->getPrice());
        $this->assertSame(60.00, $late->getPriceBeforeDiscount());
    }

    public function test_an_override_without_a_promo_is_not_marked_as_discounted(): void
    {
        $this->overridePrices = [100 => 45.00];
        $product = $this->createPaidProduct(price: 30.00);

        $this->filter($product, null, self::OCCURRENCE_ID);

        $price = $product->getProductPrices()->first();
        $this->assertSame(45.00, $price->getPrice());
        $this->assertNull($price->getPriceBeforeDiscount());
    }

    public function test_a_discounted_override_with_vat_and_a_fixed_fee_publishes_the_display_amounts(): void
    {
        $this->overridePrices = [100 => 12.50];
        $product = $this->createPaidProduct(price: 10.00, taxesAndFees: [
            $this->fee(TaxCalculationType::FIXED, 1.50),
            $this->tax(TaxCalculationType::PERCENTAGE, 23),
        ]);

        $this->filter($product, $this->percentagePromo(20), self::OCCURRENCE_ID);

        $price = $product->getProductPrices()->first();
        $this->assertSame(10.00, $price->getPrice());
        $this->assertSame(12.50, $price->getPriceBeforeDiscount());
        $this->assertSame(1.50, $price->getFeeTotal());
        $this->assertSame(2.65, $price->getTaxTotal());
        $this->assertSame(14.15, $price->getPriceIncludingTaxAndServiceFee());
        $this->assertSame(17.22, $price->getPriceBeforeDiscountIncludingTaxesAndFees());
    }

    public function test_a_promo_code_without_a_discount_type_publishes_no_struck_price(): void
    {
        $product = $this->createPaidProduct(price: 20.00, taxesAndFees: [
            $this->tax(TaxCalculationType::PERCENTAGE, 23),
        ]);
        $promoCode = (new PromoCodeDomainObject)
            ->setDiscountType(PromoCodeDiscountTypeEnum::NONE->name)
            ->setDiscountAppliesTo(PromoCodeDiscountAppliesToEnum::EACH_PRODUCT->name)
            ->setDiscount(10);

        $this->filter($product, $promoCode, null, $this->cloudDefaultEvent(), $this->cloudPlatformFeeService());

        $price = $product->getProductPrices()->first();
        $this->assertSame(20.00, $price->getPrice());
        $this->assertNull($price->getPriceBeforeDiscount());
        $this->assertNull($price->getPriceBeforeDiscountIncludingTaxesAndFees());
        $this->assertSame(24.97, $price->getPriceIncludingTaxAndServiceFee());
    }

    public function test_a_full_discount_on_a_cloud_default_event_strikes_the_full_inclusive_price(): void
    {
        $product = $this->createPaidProduct(price: 20.00, taxesAndFees: [
            $this->tax(TaxCalculationType::PERCENTAGE, 23),
        ]);

        $this->filter($product, $this->percentagePromo(100), null, $this->cloudDefaultEvent(), $this->cloudPlatformFeeService());

        $price = $product->getProductPrices()->first();
        $this->assertSame(0.00, $price->getPrice());
        $this->assertSame(0.00, $price->getPriceIncludingTaxAndServiceFee());
        $this->assertSame(20.00, $price->getPriceBeforeDiscount());
        $this->assertSame(24.97, $price->getPriceBeforeDiscountIncludingTaxesAndFees());
    }

    public function test_checkout_charges_the_same_discounted_override_the_page_displays(): void
    {
        $this->overridePrices = [100 => 12.50];
        $product = $this->createPaidProduct(price: 10.00, taxesAndFees: [
            $this->fee(TaxCalculationType::FIXED, 1.50),
            $this->tax(TaxCalculationType::PERCENTAGE, 23),
        ]);

        $this->processOrder($product, $this->percentagePromo(20), quantity: 3, eventOccurrenceId: self::OCCURRENCE_ID);

        [$line] = $this->capturedOrderItems;
        $this->assertSame(10.00, $line['price']);
        $this->assertSame(12.50, $line['price_before_discount']);
        $this->assertSame(30.00, $line['total_before_additions']);
        $this->assertSame(42.44, $line['total_gross']);
    }

    #[DataProvider('awkwardVatOrders')]
    public function test_vat_inclusive_display_and_order_totals_for_awkward_prices(
        ?PromoCodeDomainObject $promoCode,
        float $displayedPrice,
        float $displayedTax,
        float $orderTotalGross,
    ): void {
        $product = $this->createPaidProduct(price: 9.99, taxesAndFees: [
            $this->tax(TaxCalculationType::PERCENTAGE, 23),
        ]);

        $this->filter($product, $promoCode, null);

        $price = $product->getProductPrices()->first();
        $this->assertSame($displayedPrice, $price->getPrice());
        $this->assertSame($displayedTax, $price->getTaxTotal());

        $this->processOrder($this->createPaidProduct(price: 9.99, taxesAndFees: [
            $this->tax(TaxCalculationType::PERCENTAGE, 23),
        ]), $promoCode, quantity: 3);

        [$line] = $this->capturedOrderItems;
        $this->assertSame($displayedPrice, $line['price']);
        $this->assertSame($orderTotalGross, $line['total_gross']);
    }

    public static function awkwardVatOrders(): array
    {
        return [
            'no promo' => [null, 9.99, 2.30, 36.86],
            '15% promo' => [self::percentagePromo(15), 8.49, 1.95, 31.33],
            '1.33 fixed promo' => [self::fixedPromo(1.33), 8.66, 1.99, 31.96],
        ];
    }

    #[DataProvider('cloudDefaultPlatformFeePrices')]
    public function test_a_cloud_default_event_publishes_the_platform_fee_in_the_fee_total_only(
        ?float $vatRate,
        ?PromoCodeDomainObject $promoCode,
        float $displayedPrice,
        ?float $priceBeforeDiscount,
        float $displayedTax,
        float $displayedFee,
        float $displayedInclusivePrice,
        ?float $priceBeforeDiscountIncludingTaxesAndFees,
    ): void {
        $product = $this->createPaidProduct(
            price: 20.00,
            taxesAndFees: $vatRate === null ? [] : [$this->tax(TaxCalculationType::PERCENTAGE, $vatRate)],
        );

        $this->filter($product, $promoCode, null, $this->cloudDefaultEvent(), $this->cloudPlatformFeeService());

        $price = $product->getProductPrices()->first();
        $this->assertSame($displayedPrice, $price->getPrice());
        $this->assertSame($priceBeforeDiscount, $price->getPriceBeforeDiscount());
        $this->assertSame($displayedTax, $price->getTaxTotal());
        $this->assertSame($displayedFee, $price->getFeeTotal());
        $this->assertSame($displayedInclusivePrice, $price->getPriceIncludingTaxAndServiceFee());
        $this->assertSame($priceBeforeDiscountIncludingTaxesAndFees, $price->getPriceBeforeDiscountIncludingTaxesAndFees());

        $platformFee = $product->getTaxAndFees()
            ->first(fn (TaxAndFeesDomainObject $taxOrFee) => $taxOrFee->getId() === OrderPlatformFeePassThroughService::PLATFORM_FEE_ID);
        $this->assertNotNull($platformFee);
        $this->assertSame(TaxType::FEE->name, $platformFee->getType());
        $this->assertSame(TaxCalculationType::FIXED->name, $platformFee->getCalculationType());
        $this->assertEquals(0, $platformFee->getRate());
    }

    public static function cloudDefaultPlatformFeePrices(): array
    {
        return [
            'no tax, 10% promo' => [null, self::percentagePromo(10), 18.00, 20.00, 0.00, 0.27, 18.27, 20.30],
            'no tax, no promo' => [null, null, 20.00, null, 0.00, 0.30, 20.30, null],
            '23% VAT, 10% promo' => [23.0, self::percentagePromo(10), 18.00, 20.00, 4.14, 0.34, 22.48, 24.97],
            '23% VAT, no promo' => [23.0, null, 20.00, null, 4.60, 0.37, 24.97, null],
        ];
    }

    private function filter(
        ProductDomainObject $product,
        ?PromoCodeDomainObject $promoCode,
        ?int $eventOccurrenceId,
        ?EventDomainObject $event = null,
        ?OrderPlatformFeePassThroughService $platformFeeService = null,
    ): void {
        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $eventRepository->shouldReceive('findById')
            ->with(self::EVENT_ID)
            ->andReturn($event ?? (new EventDomainObject)->setId(self::EVENT_ID)->setCurrency('EUR'));

        $quantities = Mockery::mock(AvailableProductQuantitiesFetchService::class);
        $quantities->shouldReceive('getAvailableProductQuantities')
            ->andReturn(new AvailableProductQuantitiesResponseDTO(productQuantities: $product->getProductPrices()
                ->map(fn (ProductPriceDomainObject $price) => new AvailableProductQuantitiesDTO(
                    product_id: $product->getId(),
                    price_id: $price->getId(),
                    product_title: 'Ticket',
                    price_label: null,
                    quantity_available: 50,
                    quantity_reserved: 0,
                    initial_quantity_available: null,
                    product_type: ProductType::TICKET->name,
                ))
                ->values()));

        $visibility = Mockery::mock(ProductOccurrenceVisibilityRepositoryInterface::class);
        $visibility->shouldReceive('findWhere')->andReturn(collect());

        $service = new ProductFilterService(
            taxCalculationService: new TaxAndFeeCalculationService(new TaxAndFeeRollupService),
            productPriceService: $this->productPriceService(),
            fetchAvailableProductQuantitiesService: $quantities,
            platformFeeService: $platformFeeService ?? Mockery::mock(OrderPlatformFeePassThroughService::class),
            eventRepository: $eventRepository,
            productOccurrenceVisibilityRepository: $visibility,
            seatedProductLookup: Mockery::mock(SeatedProductLookupService::class, ['linksForEvent' => collect()]),
        );

        $service->filterProducts(
            products: collect([$product]),
            promoCode: $promoCode,
            hideSoldOutProducts: false,
            eventOccurrenceId: $eventOccurrenceId,
        );
    }

    private function processOrder(
        ProductDomainObject $product,
        ?PromoCodeDomainObject $promoCode,
        int $quantity,
        ?int $eventOccurrenceId = null,
    ): void {
        $this->capturedOrderItems = [];
        $event = (new EventDomainObject)->setId(self::EVENT_ID)->setCurrency('EUR');

        $orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $orderRepository->shouldReceive('addOrderItem')
            ->andReturnUsing(function (array $data) {
                $this->capturedOrderItems[] = $data;

                return Mockery::mock(OrderItemDomainObject::class);
            });

        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldReceive('loadRelation')->andReturnSelf();
        $productRepository->shouldReceive('findFirstWhere')->andReturn($product);

        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $eventRepository->shouldReceive('findById')->andReturn($event);

        $seatedProductLookup = Mockery::mock(SeatedProductLookupService::class);
        $seatedProductLookup->shouldReceive('priceAdjustmentFor')->andReturn(0);

        $eventSeatMapLookup = Mockery::mock(EventSeatMapLookupService::class);
        $eventSeatMapLookup->shouldReceive('bandOf')->andReturn(null);

        $service = new OrderItemProcessingService(
            orderRepository: $orderRepository,
            productRepository: $productRepository,
            taxCalculationService: new TaxAndFeeCalculationService(new TaxAndFeeRollupService),
            productPriceService: $this->productPriceService(),
            platformFeeService: Mockery::mock(OrderPlatformFeePassThroughService::class),
            eventRepository: $eventRepository,
            orderDiscountAllocationService: new OrderDiscountAllocationService,
            seatedProductLookup: $seatedProductLookup,
            eventSeatMapLookup: $eventSeatMapLookup,
        );

        $service->process(
            order: (new OrderDomainObject)->setId(99),
            productsOrderDetails: collect([new ProductOrderDetailsDTO(
                product_id: $product->getId(),
                quantities: collect([new OrderProductPriceDTO(quantity: $quantity, price_id: 100)]),
                event_occurrence_id: $eventOccurrenceId,
            )]),
            event: $event,
            promoCode: $promoCode,
        );
    }

    private function cloudDefaultEvent(): EventDomainObject
    {
        return (new EventDomainObject)
            ->setId(self::EVENT_ID)
            ->setCurrency('USD')
            ->setEventSettings((new EventSettingDomainObject)->setPassPlatformFeeToBuyer(true))
            ->setOrganizer((new OrganizerDomainObject)->setOrganizerConfiguration(
                (new OrganizerConfigurationDomainObject)->setApplicationFees([
                    'percentage' => 1.5,
                    'fixed' => 0,
                    'currency' => 'USD',
                ]),
            ));
    }

    private function cloudPlatformFeeService(): OrderPlatformFeePassThroughService
    {
        return new OrderPlatformFeePassThroughService(
            new Repository(['app' => ['saas_mode_enabled' => true]]),
            Mockery::mock(CurrencyConversionClientInterface::class),
        );
    }

    private function productPriceService(): ProductPriceService
    {
        $overrides = Mockery::mock(ProductPriceOccurrenceOverrideRepositoryInterface::class);
        $overrides->shouldReceive('findFirstWhere')
            ->andReturnUsing(fn (array $where) => array_key_exists($where['product_price_id'], $this->overridePrices)
                && $where['event_occurrence_id'] === self::OCCURRENCE_ID
                ? (new ProductPriceOccurrenceOverrideDomainObject)->setPrice($this->overridePrices[$where['product_price_id']])
                : null);

        return new ProductPriceService($overrides);
    }

    private function createPaidProduct(float $price, array $taxesAndFees = []): ProductDomainObject
    {
        return (new ProductDomainObject)
            ->setId(1)
            ->setEventId(self::EVENT_ID)
            ->setType(ProductPriceType::PAID->name)
            ->setProductType(ProductType::TICKET->name)
            ->setTitle('Ticket')
            ->setTaxAndFees(collect($taxesAndFees))
            ->setProductPrices(collect([
                (new ProductPriceDomainObject)->setId(100)->setPrice($price),
            ]));
    }

    private function fee(TaxCalculationType $calculationType, float $rate): TaxAndFeesDomainObject
    {
        return (new TaxAndFeesDomainObject)
            ->setId(1)
            ->setName('Booking fee')
            ->setType(TaxType::FEE->name)
            ->setCalculationType($calculationType->name)
            ->setRate($rate);
    }

    private function tax(TaxCalculationType $calculationType, float $rate): TaxAndFeesDomainObject
    {
        return (new TaxAndFeesDomainObject)
            ->setId(2)
            ->setName('VAT')
            ->setType(TaxType::TAX->name)
            ->setCalculationType($calculationType->name)
            ->setRate($rate);
    }

    private static function percentagePromo(float $discount): PromoCodeDomainObject
    {
        return (new PromoCodeDomainObject)
            ->setDiscountType(PromoCodeDiscountTypeEnum::PERCENTAGE->name)
            ->setDiscountAppliesTo(PromoCodeDiscountAppliesToEnum::EACH_PRODUCT->name)
            ->setDiscount($discount);
    }

    private static function fixedPromo(float $discount): PromoCodeDomainObject
    {
        return (new PromoCodeDomainObject)
            ->setDiscountType(PromoCodeDiscountTypeEnum::FIXED->name)
            ->setDiscountAppliesTo(PromoCodeDiscountAppliesToEnum::EACH_PRODUCT->name)
            ->setDiscount($discount);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
