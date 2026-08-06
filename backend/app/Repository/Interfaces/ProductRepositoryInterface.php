<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<ProductDomainObject>
 */
interface ProductRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function getQuantityRemainingForProductPrice(int $productId, int $productPriceId): int;

    public function getTaxesByProductId(int $productId): Collection;

    public function getProductsByTaxId(int $taxId): Collection;

    public function getCapacityAssignmentsByProductId(int $productId): Collection;

    public function addTaxesAndFeesToProduct(int $productId, array $taxIds): void;

    public function syncAddons(int $productId, array $addonProductIds): void;

    public function detachAddonAssociations(int $productId): void;

    /**
     * @return Collection<int, array<int>> map of addon_product_id => parent product ids
     */
    public function findParentProductIds(array $addonProductIds): Collection;

    public function addCapacityAssignmentToProducts(int $capacityAssignmentId, array $productIds): void;

    public function addCheckInListToProducts(int $checkInListId, array $productIds): void;

    public function removeCheckInListFromProducts(int $checkInListId): void;

    public function removeCapacityAssignmentFromProducts(int $capacityAssignmentId): void;

    public function bulkUpdateProductsAndCategories(int $eventId, array $productUpdates, array $categoryUpdates): void;

    public function hasAssociatedOrders(int $productId): bool;
}
