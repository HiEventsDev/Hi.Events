<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductOccurrenceVisibilityDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductOccurrenceVisibilityDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpdateProductVisibilityDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\UpdateProductVisibilityHandler;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class UpdateProductVisibilityHandlerTest extends TestCase
{
    private ProductOccurrenceVisibilityRepositoryInterface|Mockery\MockInterface $visibilityRepository;
    private ProductRepositoryInterface|Mockery\MockInterface $productRepository;
    private EventOccurrenceRepositoryInterface|Mockery\MockInterface $occurrenceRepository;
    private DatabaseManager|Mockery\MockInterface $databaseManager;
    private UpdateProductVisibilityHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visibilityRepository = Mockery::mock(ProductOccurrenceVisibilityRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->handler = new UpdateProductVisibilityHandler(
            $this->visibilityRepository,
            $this->productRepository,
            $this->occurrenceRepository,
            $this->databaseManager,
        );
    }

    private function makeProductCollection(array $ids): Collection
    {
        return collect(array_map(function ($id) {
            return new class($id) {
                public function __construct(public readonly int $id) {}
                public function offsetGet($key) { return $this->$key; }
                public function offsetExists($key): bool { return isset($this->$key); }
            };
        }, $ids));
    }

    public function testHandleCreatesVisibilityRecordsForSelectedProducts(): void
    {
        $dto = new UpdateProductVisibilityDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_ids: [5],
        );

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => 10,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => 1,
            ])
            ->andReturn($occurrence);

        $this->visibilityRepository->shouldReceive('deleteWhere')->once();

        $this->productRepository->shouldReceive('findWhere')
            ->once()
            ->with([ProductDomainObjectAbstract::EVENT_ID => 1])
            ->andReturn($this->makeProductCollection([5, 10]));

        $this->visibilityRepository->shouldReceive('create')
            ->once()
            ->with([
                ProductOccurrenceVisibilityDomainObjectAbstract::EVENT_OCCURRENCE_ID => 10,
                ProductOccurrenceVisibilityDomainObjectAbstract::PRODUCT_ID => 5,
            ]);

        $visibilityRecords = collect([Mockery::mock(ProductOccurrenceVisibilityDomainObject::class)]);
        $this->visibilityRepository->shouldReceive('findWhere')
            ->once()
            ->with([ProductOccurrenceVisibilityDomainObjectAbstract::EVENT_OCCURRENCE_ID => 10])
            ->andReturn($visibilityRecords);

        $result = $this->handler->handle($dto);

        $this->assertCount(1, $result);
    }

    public function testHandleReturnsEmptyWhenAllProductsSelected(): void
    {
        $dto = new UpdateProductVisibilityDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_ids: [5, 10],
        );

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($occurrence);
        $this->visibilityRepository->shouldReceive('deleteWhere')->once();

        $this->productRepository->shouldReceive('findWhere')->once()->andReturn($this->makeProductCollection([5, 10]));

        $this->visibilityRepository->shouldNotReceive('create');

        $result = $this->handler->handle($dto);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    public function testHandleThrowsWhenOccurrenceNotFound(): void
    {
        $dto = new UpdateProductVisibilityDTO(
            event_id: 1,
            event_occurrence_id: 999,
            product_ids: [5],
        );

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($dto);
    }

    public function testHandleThrowsWhenProductIdDoesNotBelongToEvent(): void
    {
        $dto = new UpdateProductVisibilityDTO(
            event_id: 1,
            event_occurrence_id: 10,
            product_ids: [5, 999],
        );

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn($occurrence);
        $this->visibilityRepository->shouldReceive('deleteWhere')->once();

        $this->productRepository->shouldReceive('findWhere')->once()->andReturn($this->makeProductCollection([5]));

        $this->visibilityRepository->shouldNotReceive('create');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($dto);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
