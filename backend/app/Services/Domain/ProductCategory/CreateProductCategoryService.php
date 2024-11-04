<?php

namespace HiEvents\Services\Domain\ProductCategory;

use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;

class CreateProductCategoryService
{
    public function __construct(
        private readonly ProductCategoryRepositoryInterface $productCategoryRepository,
    )
    {
    }

    public function createCategory(
        string  $name,
        bool    $isHidden,
        int     $eventId,
        ?string $description,
        ?string $noProductsMessage,
    ): ProductCategoryDomainObject
    {
        return $this->productCategoryRepository->create([
            'name' => $name,
            'description' => $description,
            'is_hidden' => $isHidden,
            'event_id' => $eventId,
            'order' => $this->productCategoryRepository->getNextOrder($eventId),
            'no_products_message' => $noProductsMessage,
        ]);
    }
}
