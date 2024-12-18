<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<ProductDomainObject>
 */
interface ProductRepositoryInterface extends RepositoryInterface
{
    /**
     * @param int $eventId
     * @param QueryParamsDTO $params
     * @return LengthAwarePaginator
     */
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    /**
     * @param int $productId
     * @param int $productPriceId
     * @return int
     */
    public function getQuantityRemainingForProductPrice(int $productId, int $productPriceId): int;

    /**
     * @param int $productId
     * @return Collection
     */
    public function getTaxesByProductId(int $productId): Collection;

    /**
     * @param int $taxId
     * @return Collection
     */
    public function getProductsByTaxId(int $taxId): Collection;

    /**
     * @param int $productId
     * @return Collection
     */
    public function getCapacityAssignmentsByProductId(int $productId): Collection;

    /**
     * @param int $productId
     * @param array $taxIds
     * @return void
     */
    public function addTaxesAndFeesToProduct(int $productId, array $taxIds): void;

    /**
     * @param array $productIds
     * @param int $capacityAssignmentId
     * @return void
     */
    public function addCapacityAssignmentToProducts(int $capacityAssignmentId, array $productIds): void;

    /**
     * @param int $checkInListId
     * @param array $productIds
     * @return void
     */
    public function addCheckInListToProducts(int $checkInListId, array $productIds): void;

    /**
     * @param int $checkInListId
     * @return void
     */
    public function removeCheckInListFromProducts(int $checkInListId): void;

    /**
     * @param int $capacityAssignmentId
     * @return void
     */
    public function removeCapacityAssignmentFromProducts(int $capacityAssignmentId): void;


    /**
     * @param int $eventId
     * @param array $productUpdates
     * @param array $categoryUpdates
     * @return void
     */
    public function bulkUpdateProductsAndCategories(int $eventId, array $productUpdates, array $categoryUpdates): void;

    public function hasAssociatedOrders(int $productId): bool;
}
