<?php

namespace HiEvents\Services\Application\Handlers\Product;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;

readonly class SortProductsHandler
{
    public function __construct(
        private ProductRepositoryInterface         $productRepository,
        private ProductCategoryRepositoryInterface $productCategoryRepository,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, array $sortData): void
    {
        $categories = $this->productCategoryRepository
            ->loadRelation(ProductDomainObject::class)
            ->findWhere(['event_id' => $eventId]);

        $existingCategoryIds = $categories->map(fn($category) => $category->getId())->toArray();
        $existingProductIds = $categories->flatMap(fn($category) => $category->products->map(fn($product) => $product->getId()))->toArray();

        $orderedCategoryIds = collect($sortData)->pluck('product_category_id')->toArray();
        $orderedProductIds = collect($sortData)
            ->flatMap(fn($category) => collect($category['sorted_products'])->pluck('id'))
            ->toArray();

        if (array_diff($existingCategoryIds, $orderedCategoryIds) || array_diff($orderedCategoryIds, $existingCategoryIds)) {
            throw new ResourceConflictException(
                __('The ordered category IDs must exactly match all categories for the event without missing or extra IDs.')
            );
        }

        if (array_diff($existingProductIds, $orderedProductIds) || array_diff($orderedProductIds, $existingProductIds)) {
            throw new ResourceConflictException(
                __('The ordered product IDs must exactly match all products for the event without missing or extra IDs.')
            );
        }

        $productUpdates = [];
        $categoryUpdates = [];

        foreach ($sortData as $categoryIndex => $category) {
            $categoryId = $category['product_category_id'];
            $categoryUpdates[] = [
                'id' => $categoryId,
                'order' => $categoryIndex + 1,
            ];

            foreach ($category['sorted_products'] as $productIndex => $product) {
                $productUpdates[] = [
                    'id' => $product['id'],
                    'order' => $productIndex + 1,
                    'product_category_id' => $categoryId,
                ];
            }
        }

        $this->productRepository->bulkUpdateProductsAndCategories(
            eventId: $eventId,
            productUpdates: $productUpdates,
            categoryUpdates: $categoryUpdates,
        );
    }
}
