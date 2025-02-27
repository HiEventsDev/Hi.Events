<?php

namespace HiEvents\Services\Application\Handlers\ProductCategory;

use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;

class GetProductCategoryHandler
{
    public function __construct(
        private readonly ProductCategoryRepositoryInterface $productCategoryRepository,
    )
    {
    }

    public function handle(int $eventId, int $productCategoryId): ProductCategoryDomainObject
    {
        return $this->productCategoryRepository
            ->loadRelation(new Relationship(
                domainObject: ProductDomainObject::class,
                nested: [
                    new Relationship(ProductPriceDomainObject::class),
                    new Relationship(TaxAndFeesDomainObject::class),
                ],
                orderAndDirections: [
                    new OrderAndDirection(
                        order: ProductDomainObjectAbstract::ORDER,
                    ),
                ],
            ))
            ->findFirstWhere(
                where: [
                    'event_id' => $eventId,
                    'id' => $productCategoryId,
                ]
            );
    }
}
