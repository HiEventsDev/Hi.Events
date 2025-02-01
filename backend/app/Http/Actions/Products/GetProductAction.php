<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Products;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Resources\Product\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GetProductAction extends BaseAction
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function __invoke(int $eventId, int $productId): JsonResponse|Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $product = $this->productRepository
            ->loadRelation(TaxAndFeesDomainObject::class)
            ->loadRelation(ProductPriceDomainObject::class)
            ->findFirstWhere([
                ProductDomainObjectAbstract::EVENT_ID => $eventId,
                ProductDomainObjectAbstract::ID => $productId,
            ]);

        if ($product === null) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(ProductResource::class, $product);
    }
}
