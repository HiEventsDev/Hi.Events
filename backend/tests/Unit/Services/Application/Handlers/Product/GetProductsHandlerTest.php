<?php

namespace Tests\Unit\Services\Application\Handlers\Product;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Product\GetProductsHandler;
use HiEvents\Services\Domain\Product\ProductFilterService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GetProductsHandlerTest extends TestCase
{
    private ProductRepositoryInterface|MockInterface $productRepository;

    private ProductFilterService|MockInterface $productFilterService;

    private GetProductsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->productFilterService = Mockery::mock(ProductFilterService::class);

        $this->handler = new GetProductsHandler(
            productRepository: $this->productRepository,
            productFilterService: $this->productFilterService,
        );
    }

    public function test_handle_filters_the_paginated_products_as_a_flat_collection(): void
    {
        $product = (new ProductDomainObject)->setId(1)->setEventId(10);
        $paginator = new LengthAwarePaginator(collect([$product]), 1, 25);
        $queryParams = new QueryParamsDTO;

        $this->productRepository->shouldReceive('loadRelation')->twice()->andReturnSelf();
        $this->productRepository->shouldReceive('findByEventId')
            ->once()
            ->with(10, $queryParams)
            ->andReturn($paginator);

        $filteredProducts = collect([$product]);
        $this->productFilterService->shouldReceive('filterProducts')
            ->once()
            ->withArgs(static function (Collection $products, $promoCode = null, bool $hideSoldOutProducts = true, $eventOccurrenceId = null) use ($product) {
                return $products->all() === [$product] && $hideSoldOutProducts === false;
            })
            ->andReturn($filteredProducts);

        $result = $this->handler->handle(eventId: 10, queryParamsDTO: $queryParams);

        $this->assertSame($paginator, $result);
        $this->assertSame($filteredProducts, $result->getCollection());
    }
}
