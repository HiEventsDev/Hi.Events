<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence\PriceOverride;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\ProductPriceOccurrenceOverrideDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\DTO\UpsertPriceOverrideDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\UpsertPriceOverrideHandler;
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
    private DatabaseManager|MockInterface $databaseManager;
    private UpsertPriceOverrideHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->overrideRepository = Mockery::mock(ProductPriceOccurrenceOverrideRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->productPriceRepository = Mockery::mock(ProductPriceRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->handler = new UpsertPriceOverrideHandler(
            $this->overrideRepository,
            $this->occurrenceRepository,
            $this->productPriceRepository,
            $this->productRepository,
            $this->databaseManager,
        );
    }

    private function mockOwnershipChecks(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $priceMock = Mockery::mock(ProductPriceDomainObject::class);
        $priceMock->shouldReceive('getProductId')->andReturn(5);
        $this->productPriceRepository
            ->shouldReceive('findFirst')
            ->andReturn($priceMock);

        $this->productRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(Mockery::mock(ProductDomainObject::class));
    }

    public function testHandleCreatesNewOverrideWhenNoneExists(): void
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
            ])
            ->andReturn($expectedOverride);

        $result = $this->handler->handle($dto);

        $this->assertSame($expectedOverride, $result);
    }

    public function testHandleUpdatesExistingOverride(): void
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
            ])
            ->andReturn($updatedOverride);

        $result = $this->handler->handle($dto);

        $this->assertSame($updatedOverride, $result);
    }

    public function testHandlePassesCorrectEventOccurrenceId(): void
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

    public function testHandlePassesCorrectProductPriceId(): void
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

    public function testHandlePassesCorrectPrice(): void
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
