<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;

class ProductOrderingService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    )
    {
    }

    public function getOrderForNewProduct(int $eventId, int $productCategoryId): int
    {
        return ($this->productRepository->findWhere([
                'event_id' => $eventId,
                'product_category_id' => $productCategoryId,
            ])
                ->max((static fn(ProductDomainObject $product) => $product->getOrder())) ?? 0) + 1;
    }
}
