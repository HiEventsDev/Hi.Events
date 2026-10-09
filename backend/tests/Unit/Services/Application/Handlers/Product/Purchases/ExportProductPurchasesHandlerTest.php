<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Product\Purchases;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseFilterDTO;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Product\Purchases\ExportProductPurchasesHandler;
use HiEvents\Services\Application\Handlers\Product\Purchases\ProductPurchaseProductGuard;
use Illuminate\Support\LazyCollection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ExportProductPurchasesHandlerTest extends TestCase
{
    private OrderItemRepositoryInterface|MockInterface $orderItemRepository;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private ProductRepositoryInterface|MockInterface $productRepository;

    private ExportProductPurchasesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $this->handler = new ExportProductPurchasesHandler(
            $this->orderItemRepository,
            $this->eventRepository,
            new ProductPurchaseProductGuard($this->productRepository),
        );
    }

    public function test_exports_every_product_in_the_event_timezone(): void
    {
        $filter = new ProductPurchaseFilterDTO(eventId: 3);
        $purchases = LazyCollection::make([]);

        $this->productRepository->shouldNotReceive('findFirstWhere');
        $this->orderItemRepository->shouldReceive('getAllProductPurchases')->once()->with($filter)->andReturn($purchases);
        $this->eventRepository->shouldReceive('findById')->once()->with(3)
            ->andReturn((new EventDomainObject)->setTimezone('Europe/Dublin'));

        $export = $this->handler->handle($filter);

        $this->assertSame($purchases, $export->purchases);
        $this->assertSame('Europe/Dublin', $export->timezone);
    }

    public function test_rejects_a_product_from_another_event(): void
    {
        $this->productRepository->shouldReceive('findFirstWhere')
            ->with(['id' => 7, 'event_id' => 3])
            ->andReturnNull();
        $this->orderItemRepository->shouldNotReceive('getAllProductPurchases');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(new ProductPurchaseFilterDTO(eventId: 3, productId: 7));
    }
}
