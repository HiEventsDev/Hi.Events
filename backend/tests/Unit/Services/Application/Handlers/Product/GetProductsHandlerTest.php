<?php

namespace Tests\Unit\Services\Application\Handlers\Product;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
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
            $this->productRepository,
            $this->productFilterService
        );
    }

    public function test_handle_calls_filter_and_returns_paginator(): void
    {
        $eventId = 1;
        $queryParams = QueryParamsDTO::fromArray([]);

        $product1 = new ProductDomainObject;
        $product1->setId(10);
        $product1->setEventId($eventId);

        $productsCollection = collect([$product1]);

        $paginator = new LengthAwarePaginator($productsCollection, 1, 15);

        $this->productRepository
            ->shouldReceive('loadRelation')
            ->with(ProductPriceDomainObject::class)
            ->andReturnSelf();

        $this->productRepository
            ->shouldReceive('loadRelation')
            ->with(TaxAndFeesDomainObject::class)
            ->andReturnSelf();

        $this->productRepository
            ->shouldReceive('findByEventId')
            ->with($eventId, $queryParams)
            ->andReturn($paginator);

        // Here we mock the behavior of ProductFilterService::filterProducts.
        $this->productFilterService
            ->shouldReceive('filterProducts')
            ->with(
                Mockery::on(function ($collection) {
                    return $collection instanceof Collection && $collection->first() instanceof ProductDomainObject;
                }),
                null,
                false
            )
            ->andReturn($productsCollection);

        $result = $this->handler->handle($eventId, $queryParams);

        $this->assertSame($paginator, $result);
        $this->assertSame($productsCollection, $result->getCollection());
    }

    public function test_real_filter_throws_on_product_collection(): void
    {
        $taxService = Mockery::mock(\HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService::class);
        $priceService = Mockery::mock(\HiEvents\Services\Domain\Product\ProductPriceService::class);
        $fetchService = Mockery::mock(\HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService::class);
        $feeService = Mockery::mock(\HiEvents\Services\Domain\Order\OrderPlatformFeePassThroughService::class);
        $accountRepo = Mockery::mock(\HiEvents\Repository\Interfaces\AccountRepositoryInterface::class);
        $eventRepo = Mockery::mock(\HiEvents\Repository\Interfaces\EventRepositoryInterface::class);

        $filterService = new ProductFilterService(
            $taxService,
            $priceService,
            $fetchService,
            $feeService,
            $accountRepo,
            $eventRepo
        );

        $product = new ProductDomainObject;

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches('/must be of type.*ProductCategoryDomainObject.*ProductDomainObject given/');

        $filterService->filter(collect([$product]));
    }
}
