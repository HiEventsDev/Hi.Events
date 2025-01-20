<?php

namespace HiEvents\Services\Domain\ProductCategory;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;

class CreateProductCategoryService
{
    public function __construct(
        private readonly ProductCategoryRepositoryInterface $productCategoryRepository,
    )
    {
    }

    public function createCategory(ProductCategoryDomainObject $productCategoryDomainObject): ProductCategoryDomainObject
    {
        return $this->productCategoryRepository->create(array_filter($productCategoryDomainObject->toArray()));
    }

    public function createDefaultProductCategory(EventDomainObject $event): void
    {
        $this->createCategory((new ProductCategoryDomainObject())
            ->setEventId($event->getId())
            ->setName(__('Tickets'))
            ->setIsHidden(false)
            ->setNoProductsMessage(__('There are no tickets available for this event'))
        );
    }
}
