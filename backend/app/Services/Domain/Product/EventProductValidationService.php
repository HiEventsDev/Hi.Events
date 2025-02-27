<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;

class EventProductValidationService
{
    public function __construct(
        readonly private ProductRepositoryInterface $productRepository,
    )
    {
    }

    /**
     * @throws UnrecognizedProductIdException
     */
    public function validateProductIds(array $productIds, int $eventId): void
    {
        $validProductIds = $this->productRepository->findWhere([
            'event_id' => $eventId,
        ])->map(fn(ProductDomainObject $product) => $product->getId())
            ->toArray();

        $invalidProductIds = array_diff($productIds, $validProductIds);

        if (!empty($invalidProductIds)) {
            throw new UnrecognizedProductIdException(
                __('Invalid product ids: :ids', ['ids' => implode(', ', $invalidProductIds)])
            );
        }
    }
}
