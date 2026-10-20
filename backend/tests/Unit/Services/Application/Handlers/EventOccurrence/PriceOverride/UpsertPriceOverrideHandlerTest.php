<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence\PriceOverride;

use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\ProductPriceOccurrenceOverrideDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\Exceptions\InvalidOccurrenceQuantityOverrideException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\DTO\UpsertPriceOverrideDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\UpsertPriceOverrideHandler;
use HiEvents\Services\Domain\Product\SoldAndReservedQuantitiesService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpsertPriceOverrideHandlerTest extends TestCase
{
    private ProductPriceOccurrenceOverrideRepositoryInterface|MockInterface $overrideRepository;

    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private ProductPriceRepositoryInterface|MockInterface $productPriceRepository;

    private ProductRepositoryInterface|MockInterface $productRepository;

    private SoldAndReservedQuantitiesService|MockInterface $soldAndReservedQuantities;

    private DatabaseManager|MockInterface $databaseManager;

    private UpsertPriceOverrideHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->overrideRepository = Mockery::mock(ProductPriceOccurrenceOverrideRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->productPriceRepository = Mockery::mock(ProductPriceRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->soldAndReservedQuantities = Mockery::mock(SoldAndReservedQuantitiesService::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new UpsertPriceOverrideHandler(
            $this->overrideRepository,
            $this->occurrenceRepository,
            $this->productPriceRepository,
            $this->productRepository,
            $this->soldAndReservedQuantities,
            $this->databaseManager,
        );
    }

    private function mockOwnershipChecks(
        string $quantityAppliesTo = ProductQuantityAppliesTo::OCCURRENCE->name,
        string $productType = ProductType::TICKET->name,
    ): void {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $priceMock = Mockery::mock(ProductPriceDomainObject::class);
        $priceMock->shouldReceive('getProductId')->andReturn(5);
        $priceMock->shouldReceive('getQuantityAppliesTo')->andReturn($quantityAppliesTo);
        $priceMock->shouldReceive('getLabel')->andReturn('Early Bird');
        $this->productPriceRepository
            ->shouldReceive('findFirst')
            ->andReturn($priceMock);

        $productMock = Mockery::mock(ProductDomainObject::class);
        $productMock->shouldReceive('getProductType')->andReturn($productType);
        $this->productRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($productMock);
    }

    public function test_handle_creates_new_override_when_none_exists(): void
    {
        $this->mockOwnershipChecks();

        $dto = new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_price_id: 20,
            price: 99.99,
        );

        $expectedOverride = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);

        $this->overrideRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => 10,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRODUCT_PRICE_ID => 20,
            ])
            ->andReturn(null);

        $this->overrideRepository
            ->shouldReceive('create')
            ->once()
            ->with([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => 10,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRODUCT_PRICE_ID => 20,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRICE => 99.99,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::QUANTITY_AVAILABLE => null,
            ])
            ->andReturn($expectedOverride);

        $result = $this->handler->handle($dto);

        $this->assertSame($expectedOverride, $result);
    }

    public function test_handle_updates_existing_override(): void
    {
        $this->mockOwnershipChecks();

        $dto = new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_price_id: 20,
            price: 149.99,
        );

        $existingOverride = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);
        $existingOverride->shouldReceive('getId')->andReturn(5);

        $updatedOverride = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);

        $this->overrideRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => 10,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRODUCT_PRICE_ID => 20,
            ])
            ->andReturn($existingOverride);

        $this->overrideRepository
            ->shouldNotReceive('create');

        $this->overrideRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(5, [
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRICE => 149.99,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::QUANTITY_AVAILABLE => null,
            ])
            ->andReturn($updatedOverride);

        $result = $this->handler->handle($dto);

        $this->assertSame($updatedOverride, $result);
    }

    public function test_handle_passes_correct_event_occurrence_id(): void
    {
        $occurrenceId = 42;
        $this->mockOwnershipChecks();

        $dto = new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: $occurrenceId,
            product_price_id: 1,
            price: 50.00,
        );

        $expectedOverride = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);

        $this->overrideRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(Mockery::on(function ($arg) use ($occurrenceId) {
                return $arg[ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID] === $occurrenceId;
            }))
            ->andReturn(null);

        $this->overrideRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($occurrenceId) {
                return $arg[ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID] === $occurrenceId;
            }))
            ->andReturn($expectedOverride);

        $result = $this->handler->handle($dto);

        $this->assertSame($expectedOverride, $result);
    }

    public function test_handle_passes_correct_product_price_id(): void
    {
        $priceId = 77;
        $this->mockOwnershipChecks();

        $dto = new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 1,
            product_price_id: $priceId,
            price: 25.00,
        );

        $expectedOverride = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);

        $this->overrideRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(Mockery::on(function ($arg) use ($priceId) {
                return $arg[ProductPriceOccurrenceOverrideDomainObjectAbstract::PRODUCT_PRICE_ID] === $priceId;
            }))
            ->andReturn(null);

        $this->overrideRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($priceId) {
                return $arg[ProductPriceOccurrenceOverrideDomainObjectAbstract::PRODUCT_PRICE_ID] === $priceId;
            }))
            ->andReturn($expectedOverride);

        $result = $this->handler->handle($dto);

        $this->assertSame($expectedOverride, $result);
    }

    public function test_handle_passes_correct_price(): void
    {
        $price = 199.50;
        $this->mockOwnershipChecks();

        $dto = new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 1,
            product_price_id: 2,
            price: $price,
        );

        $expectedOverride = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);

        $this->overrideRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn(null);

        $this->overrideRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($price) {
                return $arg[ProductPriceOccurrenceOverrideDomainObjectAbstract::PRICE] === $price;
            }))
            ->andReturn($expectedOverride);

        $result = $this->handler->handle($dto);

        $this->assertSame($expectedOverride, $result);
    }

    public function test_it_throws_when_occurrence_not_found_for_event(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn(null);

        $dto = new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 99,
            product_price_id: 20,
            price: 49.99,
        );

        $this->handler->handle($dto);
    }

    public function test_it_throws_when_product_price_not_found(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $this->productPriceRepository
            ->shouldReceive('findFirst')
            ->once()
            ->andReturn(null);

        $dto = new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_price_id: 99,
            price: 49.99,
        );

        $this->handler->handle($dto);
    }

    public function test_it_throws_when_product_not_belonging_to_event(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $priceMock = Mockery::mock(ProductPriceDomainObject::class);
        $priceMock->shouldReceive('getProductId')->andReturn(5);
        $this->productPriceRepository
            ->shouldReceive('findFirst')
            ->once()
            ->andReturn($priceMock);

        $this->productRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn(null);

        $dto = new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_price_id: 20,
            price: 49.99,
        );

        $this->handler->handle($dto);
    }

    public function test_it_stores_a_quantity_override_for_a_per_date_tier(): void
    {
        $this->mockOwnershipChecks();
        $this->soldAndReservedQuantities
            ->shouldReceive('getSoldByPriceForOccurrence')
            ->once()
            ->with(10, ProductType::TICKET)
            ->andReturn([20 => 3]);

        $expectedOverride = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);

        $this->overrideRepository->shouldReceive('findFirstWhere')->once()->andReturn(null);
        $this->overrideRepository
            ->shouldReceive('create')
            ->once()
            ->with([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => 10,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRODUCT_PRICE_ID => 20,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRICE => null,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::QUANTITY_AVAILABLE => 5,
            ])
            ->andReturn($expectedOverride);

        $result = $this->handler->handle(new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_price_id: 20,
            quantity_available: 5,
        ));

        $this->assertSame($expectedOverride, $result);
    }

    public function test_it_counts_general_product_sales_from_order_items(): void
    {
        $this->mockOwnershipChecks(productType: ProductType::GENERAL->name);
        $this->soldAndReservedQuantities
            ->shouldReceive('getSoldByPriceForOccurrence')
            ->once()
            ->with(10, ProductType::GENERAL)
            ->andReturn([20 => 2]);

        $this->overrideRepository->shouldReceive('findFirstWhere')->once()->andReturn(null);
        $this->overrideRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn(Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class));

        $this->handler->handle(new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_price_id: 20,
            quantity_available: 2,
        ));
    }

    public function test_it_rejects_a_quantity_override_for_an_event_wide_tier(): void
    {
        $this->mockOwnershipChecks(quantityAppliesTo: ProductQuantityAppliesTo::EVENT->name);
        $this->overrideRepository->shouldNotReceive('create');

        $this->expectException(InvalidOccurrenceQuantityOverrideException::class);

        $this->handler->handle(new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_price_id: 20,
            quantity_available: 5,
        ));
    }

    public function test_it_rejects_a_quantity_below_the_sold_count_for_that_date(): void
    {
        $this->mockOwnershipChecks();
        $this->soldAndReservedQuantities
            ->shouldReceive('getSoldByPriceForOccurrence')
            ->once()
            ->andReturn([20 => 4]);
        $this->overrideRepository->shouldNotReceive('create');

        $this->expectException(InvalidOccurrenceQuantityOverrideException::class);
        $this->expectExceptionMessageMatches('/Early Bird.*\(4\)/');

        $this->handler->handle(new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_price_id: 20,
            quantity_available: 3,
        ));
    }

    public function test_a_price_only_override_skips_the_quantity_guard(): void
    {
        $this->mockOwnershipChecks(quantityAppliesTo: ProductQuantityAppliesTo::EVENT->name);
        $this->soldAndReservedQuantities->shouldNotReceive('getSoldByPriceForOccurrence');

        $this->overrideRepository->shouldReceive('findFirstWhere')->once()->andReturn(null);
        $this->overrideRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn(Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class));

        $this->handler->handle(new UpsertPriceOverrideDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_price_id: 20,
            price: 12.50,
        ));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
