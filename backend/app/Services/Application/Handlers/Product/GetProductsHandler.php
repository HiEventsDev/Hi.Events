<?php

namespace HiEvents\Services\Application\Handlers\Product;

use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Product\ProductFilterService;
use Illuminate\Pagination\LengthAwarePaginator;

class GetProductsHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductFilterService       $productFilterService,
    )
    {
    }

    public function handle(int $eventId, QueryParamsDTO $queryParamsDTO): LengthAwarePaginator
    {
        $productPaginator = $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->loadRelation(TaxAndFeesDomainObject::class)
            ->findByEventId($eventId, $queryParamsDTO);

        $filteredProducts = $this->productFilterService->filter(
            productsCategories: $productPaginator->getCollection(),
            hideSoldOutProducts: false,
        );

        $productPaginator->setCollection($filteredProducts);

        return $productPaginator;
    }
}
