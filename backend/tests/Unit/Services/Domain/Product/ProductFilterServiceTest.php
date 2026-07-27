<?php

namespace Tests\Unit\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderPlatformFeePassThroughService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Product\ProductFilterService;
use HiEvents\Services\Domain\Product\ProductPriceService;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductFilterServiceTest extends TestCase
{
    private const EVENT_ID = 10;

    private AvailableProductQuantitiesFetchService|MockInterface $fetchAvailableProductQuantitiesService;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private ProductFilterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fetchAvailableProductQuantitiesService = Mockery::mock(AvailableProductQuantitiesFetchService::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);

        $this->service = new ProductFilterService(
            taxCalculationService: Mockery::mock(TaxAndFeeCalculationService::class),
            productPriceService: Mockery::mock(ProductPriceService::class),
            fetchAvailableProductQuantitiesService: $this->fetchAvailableProductQuantitiesService,
            platformFeeService: Mockery::mock(OrderPlatformFeePassThroughService::class),
            eventRepository: $this->eventRepository,
            productOccurrenceVisibilityRepository: Mockery::mock(ProductOccurrenceVisibilityRepositoryInterface::class),
        );
    }

    public function test_filter_products_accepts_a_flat_product_collection(): void
    {
        $product = $this->createFreeProduct(id: 1, priceId: 100);

        $this->expectAccountConfigurationLoad();
        $this->expectQuantities([
            $this->createQuantityDto(productId: 1, priceId: 100, quantityAvailable: 5),
        ]);

        $result = $this->service->filterProducts(
            products: collect([$product]),
            hideSoldOutProducts: false,
        );

        $this->assertSame([$product], $result->all());
        $this->assertSame(5, $product->getProductPrices()->first()->getQuantityAvailable());
        $this->assertTrue($product->getProductPrices()->first()->isAvailable());
    }

    public function test_filter_products_rejects_hidden_products_when_hiding(): void
    {
        $visible = $this->createFreeProduct(id: 1, priceId: 100);
        $hidden = $this->createFreeProduct(id: 2, priceId: 200)->setIsHidden(true);

        $this->expectAccountConfigurationLoad();
        $this->expectQuantities([
            $this->createQuantityDto(productId: 1, priceId: 100, quantityAvailable: 5),
            $this->createQuantityDto(productId: 2, priceId: 200, quantityAvailable: 5),
        ]);

        $result = $this->service->filterProducts(collect([$visible, $hidden]));

        $this->assertSame([$visible], $result->all());
    }

    public function test_filter_products_returns_empty_collection_untouched(): void
    {
        $result = $this->service->filterProducts(collect());

        $this->assertTrue($result->isEmpty());
    }

    public function test_filter_attaches_filtered_products_to_visible_categories(): void
    {
        $product = $this->createFreeProduct(id: 1, priceId: 100, productCategoryId: 5);
        $visibleCategory = (new ProductCategoryDomainObject)->setId(5);
        $visibleCategory->setProducts(collect([$product]));
        $hiddenCategory = (new ProductCategoryDomainObject)->setId(6)->setIsHidden(true);
        $hiddenCategory->setProducts(collect());

        $this->expectAccountConfigurationLoad();
        $this->expectQuantities([
            $this->createQuantityDto(productId: 1, priceId: 100, quantityAvailable: 5),
        ]);

        $result = $this->service->filter(
            productsCategories: collect([$visibleCategory, $hiddenCategory]),
            hideSoldOutProducts: false,
        );

        $this->assertSame([$visibleCategory], $result->all());
        $this->assertSame([$product], $result->first()->getProducts()->values()->all());
    }

    private function createFreeProduct(int $id, int $priceId, int $productCategoryId = 5): ProductDomainObject
    {
        return (new ProductDomainObject)
            ->setId($id)
            ->setEventId(self::EVENT_ID)
            ->setProductCategoryId($productCategoryId)
            ->setType(ProductPriceType::FREE->name)
            ->setProductPrices(collect([
                (new ProductPriceDomainObject)
                    ->setId($priceId)
                    ->setPrice(0.00),
            ]));
    }

    private function createQuantityDto(int $productId, int $priceId, int $quantityAvailable): AvailableProductQuantitiesDTO
    {
        return new AvailableProductQuantitiesDTO(
            product_id: $productId,
            price_id: $priceId,
            product_title: 'Ticket',
            price_label: null,
            quantity_available: $quantityAvailable,
            quantity_reserved: 0,
            initial_quantity_available: null,
            product_type: 'TICKET',
        );
    }

    private function expectAccountConfigurationLoad(): void
    {
        $this->eventRepository->shouldReceive('loadRelation')->twice()->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')
            ->once()
            ->with(self::EVENT_ID)
            ->andReturn((new EventDomainObject)->setId(self::EVENT_ID)->setCurrency('USD'));
    }

    private function expectQuantities(array $quantities): void
    {
        $this->fetchAvailableProductQuantitiesService
            ->shouldReceive('getAvailableProductQuantities')
            ->once()
            ->andReturn(new AvailableProductQuantitiesResponseDTO(productQuantities: collect($quantities)));
    }
}
