<?php

namespace Tests\Unit\Services\Domain\Product;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductQuantityUpdateServiceTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    private ProductPriceRepositoryInterface|MockInterface $productPriceRepository;
    private ProductRepositoryInterface|MockInterface $productRepository;
    private CapacityAssignmentRepositoryInterface|MockInterface $capacityAssignmentRepository;
    private DatabaseManager|MockInterface $databaseManager;
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;
    private ProductQuantityUpdateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productPriceRepository = Mockery::mock(ProductPriceRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->capacityAssignmentRepository = Mockery::mock(CapacityAssignmentRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->service = new ProductQuantityUpdateService(
            $this->productPriceRepository,
            $this->productRepository,
            $this->capacityAssignmentRepository,
            $this->databaseManager,
            $this->occurrenceRepository,
        );
    }

    public function testIncreaseQuantitySoldIncrementsOccurrenceCapacity(): void
    {
        $priceId = 100;
        $occurrenceId = 5;
        $adjustment = 2;

        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getProductId')->andReturn(10);

        $this->productPriceRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => $priceId])
            ->andReturn($price);

        $this->productRepository
            ->shouldReceive('getCapacityAssignmentsByProductId')
            ->with(10)
            ->andReturn(collect());

        $this->productPriceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn($data) => array_key_exists('quantity_sold', $data)),
                ['id' => $priceId],
            );

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn($data) => array_key_exists('used_capacity', $data)),
                ['id' => $occurrenceId],
            );

        $occurrence = (new EventOccurrenceDomainObject())
            ->setId($occurrenceId)
            ->setCapacity(null)
            ->setUsedCapacity($adjustment)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name);

        $this->occurrenceRepository
            ->shouldReceive('findById')
            ->with($occurrenceId)
            ->andReturn($occurrence);

        $this->service->increaseQuantitySold($priceId, $adjustment, $occurrenceId);
    }

    public function testDecreaseQuantitySoldDecrementsOccurrenceCapacity(): void
    {
        $priceId = 100;
        $occurrenceId = 5;
        $adjustment = 1;

        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getProductId')->andReturn(10);

        $this->productPriceRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => $priceId])
            ->andReturn($price);

        $this->productRepository
            ->shouldReceive('getCapacityAssignmentsByProductId')
            ->with(10)
            ->andReturn(collect());

        $this->productPriceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn($data) => array_key_exists('quantity_sold', $data)),
                ['id' => $priceId],
            );

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn($data) => array_key_exists('used_capacity', $data)),
                ['id' => $occurrenceId],
            );

        $occurrence = (new EventOccurrenceDomainObject())
            ->setId($occurrenceId)
            ->setCapacity(10)
            ->setUsedCapacity(5)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name);

        $this->occurrenceRepository
            ->shouldReceive('findById')
            ->with($occurrenceId)
            ->andReturn($occurrence);

        $this->service->decreaseQuantitySold($priceId, $adjustment, $occurrenceId);
    }

    public function testIncreaseQuantitySoldSkipsOccurrenceWhenNull(): void
    {
        $priceId = 100;

        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getProductId')->andReturn(10);

        $this->productPriceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($price);

        $this->productRepository
            ->shouldReceive('getCapacityAssignmentsByProductId')
            ->andReturn(collect());

        $priceUpdateCalled = false;
        $this->productPriceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->andReturnUsing(function () use (&$priceUpdateCalled) {
                $priceUpdateCalled = true;
                return 1;
            });

        $this->occurrenceRepository
            ->shouldNotReceive('updateWhere');

        $this->service->increaseQuantitySold($priceId, 1, null);

        $this->assertTrue($priceUpdateCalled);
    }

    public function testUpdateQuantitiesFromOrderPassesOccurrenceId(): void
    {
        $orderItem = (new OrderItemDomainObject())
            ->setId(1)
            ->setProductPriceId(100)
            ->setQuantity(2)
            ->setEventOccurrenceId(5);

        $order = (new OrderDomainObject())
            ->setOrderItems(new Collection([$orderItem]));

        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getProductId')->andReturn(10);

        $this->productPriceRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 100])
            ->andReturn($price);

        $this->productRepository
            ->shouldReceive('getCapacityAssignmentsByProductId')
            ->with(10)
            ->andReturn(collect());

        $this->productPriceRepository
            ->shouldReceive('updateWhere')
            ->once();

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn($data) => array_key_exists('used_capacity', $data)),
                ['id' => 5],
            );

        $occurrence = (new EventOccurrenceDomainObject())
            ->setId(5)
            ->setCapacity(null)
            ->setUsedCapacity(2)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name);

        $this->occurrenceRepository
            ->shouldReceive('findById')
            ->with(5)
            ->andReturn($occurrence);

        $this->service->updateQuantitiesFromOrder($order);
    }

    public function testIncreaseQuantitySoldSetsOccurrenceToSoldOutWhenAtCapacity(): void
    {
        $priceId = 100;
        $occurrenceId = 5;

        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getProductId')->andReturn(10);

        $this->productPriceRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => $priceId])
            ->andReturn($price);

        $this->productRepository
            ->shouldReceive('getCapacityAssignmentsByProductId')
            ->with(10)
            ->andReturn(collect());

        $this->productPriceRepository
            ->shouldReceive('updateWhere')
            ->once();

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->with(
                Mockery::on(fn($data) => array_key_exists('used_capacity', $data)),
                ['id' => $occurrenceId],
            )
            ->once();

        $occurrence = (new EventOccurrenceDomainObject())
            ->setId($occurrenceId)
            ->setCapacity(10)
            ->setUsedCapacity(10)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name);

        $this->occurrenceRepository
            ->shouldReceive('findById')
            ->with($occurrenceId)
            ->andReturn($occurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->with(
                ['status' => EventOccurrenceStatus::SOLD_OUT->name],
                ['id' => $occurrenceId],
            )
            ->once();

        $this->service->increaseQuantitySold($priceId, 1, $occurrenceId);
    }

    public function testIncreaseQuantitySoldDoesNotSetSoldOutWhenCapacityIsNull(): void
    {
        $priceId = 100;
        $occurrenceId = 5;

        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getProductId')->andReturn(10);

        $this->productPriceRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => $priceId])
            ->andReturn($price);

        $this->productRepository
            ->shouldReceive('getCapacityAssignmentsByProductId')
            ->with(10)
            ->andReturn(collect());

        $this->productPriceRepository
            ->shouldReceive('updateWhere')
            ->once();

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->with(
                Mockery::on(fn($data) => array_key_exists('used_capacity', $data)),
                ['id' => $occurrenceId],
            )
            ->once();

        $occurrence = (new EventOccurrenceDomainObject())
            ->setId($occurrenceId)
            ->setCapacity(null)
            ->setUsedCapacity(100)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name);

        $this->occurrenceRepository
            ->shouldReceive('findById')
            ->with($occurrenceId)
            ->andReturn($occurrence);

        $this->service->increaseQuantitySold($priceId, 1, $occurrenceId);
    }

    public function testDecreaseQuantitySoldResetsOccurrenceFromSoldOutToActive(): void
    {
        $priceId = 100;
        $occurrenceId = 5;

        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getProductId')->andReturn(10);

        $this->productPriceRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => $priceId])
            ->andReturn($price);

        $this->productRepository
            ->shouldReceive('getCapacityAssignmentsByProductId')
            ->with(10)
            ->andReturn(collect());

        $this->productPriceRepository
            ->shouldReceive('updateWhere')
            ->once();

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->with(
                Mockery::on(fn($data) => array_key_exists('used_capacity', $data)),
                ['id' => $occurrenceId],
            )
            ->once();

        $occurrence = (new EventOccurrenceDomainObject())
            ->setId($occurrenceId)
            ->setCapacity(10)
            ->setUsedCapacity(9)
            ->setStatus(EventOccurrenceStatus::SOLD_OUT->name);

        $this->occurrenceRepository
            ->shouldReceive('findById')
            ->with($occurrenceId)
            ->andReturn($occurrence);

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->with(
                ['status' => EventOccurrenceStatus::ACTIVE->name],
                ['id' => $occurrenceId],
            )
            ->once();

        $this->service->decreaseQuantitySold($priceId, 1, $occurrenceId);
    }

    public function testDecreaseQuantitySoldDoesNotResetNonSoldOutOccurrence(): void
    {
        $priceId = 100;
        $occurrenceId = 5;

        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getProductId')->andReturn(10);

        $this->productPriceRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => $priceId])
            ->andReturn($price);

        $this->productRepository
            ->shouldReceive('getCapacityAssignmentsByProductId')
            ->with(10)
            ->andReturn(collect());

        $this->productPriceRepository
            ->shouldReceive('updateWhere')
            ->once();

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->with(
                Mockery::on(fn($data) => array_key_exists('used_capacity', $data)),
                ['id' => $occurrenceId],
            )
            ->once();

        $occurrence = (new EventOccurrenceDomainObject())
            ->setId($occurrenceId)
            ->setCapacity(10)
            ->setUsedCapacity(5)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name);

        $this->occurrenceRepository
            ->shouldReceive('findById')
            ->with($occurrenceId)
            ->andReturn($occurrence);

        $this->service->decreaseQuantitySold($priceId, 1, $occurrenceId);
    }

    public function testIncreaseQuantitySoldDoesNotOverrideCancelledStatus(): void
    {
        $priceId = 100;
        $occurrenceId = 5;

        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getProductId')->andReturn(10);

        $this->productPriceRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => $priceId])
            ->andReturn($price);

        $this->productRepository
            ->shouldReceive('getCapacityAssignmentsByProductId')
            ->with(10)
            ->andReturn(collect());

        $this->productPriceRepository
            ->shouldReceive('updateWhere')
            ->once();

        $this->occurrenceRepository
            ->shouldReceive('updateWhere')
            ->with(
                Mockery::on(fn($data) => array_key_exists('used_capacity', $data)),
                ['id' => $occurrenceId],
            )
            ->once();

        $occurrence = (new EventOccurrenceDomainObject())
            ->setId($occurrenceId)
            ->setCapacity(10)
            ->setUsedCapacity(10)
            ->setStatus(EventOccurrenceStatus::CANCELLED->name);

        $this->occurrenceRepository
            ->shouldReceive('findById')
            ->with($occurrenceId)
            ->andReturn($occurrence);

        $this->occurrenceRepository
            ->shouldNotReceive('updateWhere')
            ->with(
                ['status' => EventOccurrenceStatus::SOLD_OUT->name],
                ['id' => $occurrenceId],
            );

        $this->service->increaseQuantitySold($priceId, 1, $occurrenceId);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
