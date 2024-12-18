<?php

namespace HiEvents\Services\Application\Handlers\ProductCategory;

use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;
use HiEvents\Services\Application\Handlers\ProductCategory\DTO\UpsertProductCategoryDTO;

class EditProductCategoryHandler
{
    public function __construct(
        private readonly ProductCategoryRepositoryInterface $productCategoryRepository,
    )
    {
    }

    public function handle(UpsertProductCategoryDTO $dto): ProductCategoryDomainObject
    {
        $this->productCategoryRepository->updateWhere(
            attributes: [
                'name' => $dto->name,
                'is_hidden' => $dto->is_hidden,
                'description' => $dto->description,
                'no_products_message' => $dto->no_products_message ?? __('There are no products available in this category'),
            ],
            where: [
                'id' => $dto->product_category_id,
                'event_id' => $dto->event_id,
            ],
        );

        return $this->productCategoryRepository->findById($dto->product_category_id);
    }
}
