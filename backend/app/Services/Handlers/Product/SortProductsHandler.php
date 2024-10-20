<?php

namespace HiEvents\Services\Handlers\Product;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;

readonly class SortProductsHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, array $data): void
    {
        $orderedProductIds = collect($data)->sortBy('order')->pluck('id')->toArray();

        $productIdsResult = $this->productRepository->findWhere([
            'event_id' => $eventId,
        ])
            ->map(fn($product) => $product->getId())
            ->toArray();

        // Check if the orderedProductIds array exactly matches the product IDs from the database
        $missingInOrdered = array_diff($productIdsResult, $orderedProductIds);
        $extraInOrdered = array_diff($orderedProductIds, $productIdsResult);

        if (!empty($missingInOrdered) || !empty($extraInOrdered)) {
            throw new ResourceConflictException(
                __('The ordered product IDs must exactly match all products for the event without missing or extra IDs.')
            );
        }

        $this->productRepository->sortProducts($eventId, $orderedProductIds);
    }
}
