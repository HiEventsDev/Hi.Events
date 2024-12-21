<?php

namespace HiEvents\Services\Domain\ProductCategory;

use HiEvents\DomainObjects\Generated\ProductCategoryDomainObjectAbstract;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetProductCategoryService
{
    public function __construct(
        private readonly ProductCategoryRepositoryInterface $productCategoryRepository,
    )
    {
    }

    public function getCategory(int $categoryId, int $eventId): ProductCategoryDomainObject
    {
        $category = $this->productCategoryRepository
            ->loadRelation(new Relationship(
                domainObject: ProductDomainObject::class,
                orderAndDirections: [
                    new OrderAndDirection(
                        order: ProductCategoryDomainObjectAbstract::ORDER,
                    ),
                ],
            ))
            ->findFirstWhere(
                where: [
                    'id' => $categoryId,
                    'event_id' => $eventId,
                ]
            );

        if (!$category) {
            throw new ResourceNotFoundException(
                __('The product category with ID :id was not found.', ['id' => $categoryId])
            );
        }

        return $category;
    }
}
