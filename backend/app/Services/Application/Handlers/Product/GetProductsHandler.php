<?php

namespace HiEvents\Services\Application\Handlers\Product;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Product\ProductFilterService;
use Illuminate\Pagination\LengthAwarePaginator;

class GetProductsHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductFilterService $productFilterService,
    ) {}

    public function handle(int $eventId, QueryParamsDTO $queryParamsDTO): LengthAwarePaginator
    {
        $productPaginator = $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->loadRelation(TaxAndFeesDomainObject::class)
            ->loadRelation(new Relationship(domainObject: ProductDomainObject::class, name: 'addons'))
            ->findByEventId($eventId, $queryParamsDTO);

        $filteredProducts = $this->productFilterService->filterProducts(
            products: $productPaginator->getCollection(),
            hideSoldOutProducts: false,
        );

        $productPaginator->setCollection($filteredProducts);

        return $productPaginator;
    }
}
