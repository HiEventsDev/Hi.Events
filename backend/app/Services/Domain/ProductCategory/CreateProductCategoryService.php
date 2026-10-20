<?php

namespace HiEvents\Services\Domain\ProductCategory;

use HiEvents\DomainObjects\Enums\ProductTerminology;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;

class CreateProductCategoryService
{
    public function __construct(
        private readonly ProductCategoryRepositoryInterface $productCategoryRepository,
    ) {}

    public function createCategory(ProductCategoryDomainObject $productCategoryDomainObject): ProductCategoryDomainObject
    {
        return $this->productCategoryRepository->create(array_filter($productCategoryDomainObject->toArray()));
    }

    public function createDefaultProductCategory(EventDomainObject $event): void
    {
        $terminology = ProductTerminology::forCategory($event->getCategory());

        $this->createCategory((new ProductCategoryDomainObject)
            ->setEventId($event->getId())
            ->setName($terminology->defaultProductCategoryName())
            ->setIsHidden(false)
            ->setNoProductsMessage($terminology->defaultNoProductsMessage())
        );
    }
}
