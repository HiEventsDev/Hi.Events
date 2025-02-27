<?php

namespace HiEvents\Services\Application\Handlers\ProductCategory;

use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\Services\Application\Handlers\ProductCategory\DTO\UpsertProductCategoryDTO;
use HiEvents\Services\Domain\ProductCategory\CreateProductCategoryService;

class CreateProductCategoryHandler
{
    public function __construct(
        private readonly CreateProductCategoryService $productCategoryService,
    )
    {
    }

    public function handle(UpsertProductCategoryDTO $dto): ProductCategoryDomainObject
    {
        $productCategory = new ProductCategoryDomainObject();
        $productCategory->setName($dto->name);
        $productCategory->setIsHidden($dto->is_hidden);
        $productCategory->setEventId($dto->event_id);
        $productCategory->setDescription($dto->description);
        $productCategory->setNoProductsMessage(
            $dto->no_products_message ?? __('There are no products available in this category'
        ));

        return $this->productCategoryService->createCategory($productCategory);
    }
}
