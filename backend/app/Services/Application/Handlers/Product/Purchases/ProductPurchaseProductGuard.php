<?php

namespace HiEvents\Services\Application\Handlers\Product\Purchases;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;

class ProductPurchaseProductGuard
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function assertProductBelongsToEvent(int $eventId, int $productId): void
    {
        $product = $this->productRepository->findFirstWhere([
            'id' => $productId,
            'event_id' => $eventId,
        ]);

        if ($product === null) {
            throw new ResourceNotFoundException(__('Product not found'));
        }
    }
}
