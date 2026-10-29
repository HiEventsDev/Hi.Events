<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Product\Exception\InvalidAddonProductException;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;

readonly class ProductAddonAssociationService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private EventProductValidationService $eventProductValidationService,
    ) {}

    /**
     * @throws InvalidAddonProductException
     * @throws UnrecognizedProductIdException
     */
    public function associateAddons(int $productId, int $eventId, array $addonProductIds): void
    {
        $addonProductIds = array_values(array_unique(array_map('intval', $addonProductIds)));

        if (in_array($productId, $addonProductIds, true)) {
            throw new InvalidAddonProductException(__('A product cannot be an add-on of itself'));
        }

        if ($addonProductIds !== []) {
            $this->eventProductValidationService->validateProductIds($addonProductIds, $eventId);
        }

        $this->productRepository->syncAddons($productId, $addonProductIds);
    }
}
