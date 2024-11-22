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
        return $this->productCategoryService->createCategory(
            name: $dto->name,
            isHidden: $dto->is_hidden,
            eventId: $dto->event_id,
            description: $dto->description,
            noProductsMessage: $dto->no_products_message,
        );
    }
}
