<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseDTO;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseFilterDTO;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseSummaryDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

/**
 * @extends RepositoryInterface<OrderItemDomainObject>
 */
interface OrderItemRepositoryInterface extends RepositoryInterface
{
    public function getReservedTicketQuantityForOccurrence(int $occurrenceId): int;

    /**
     * @param  int[]  $eventIds
     * @return array<int, int> product_price_id => quantity
     */
    public function getReservedQuantitiesByPrice(array $eventIds, ?int $occurrenceId = null): array;

    /**
     * @return array<int, int> product_price_id => quantity
     */
    public function getSoldQuantitiesByPriceForOccurrence(int $occurrenceId): array;

    /**
     * @return array<int, int> product_price_id => highest quantity sold on any single occurrence
     */
    public function getMaxSoldPerOccurrenceByPrice(array $productPriceIds): array;

    /**
     * @param  int[]  $productIds
     * @return int[] product ids held by an unexpired reservation
     */
    public function getProductIdsInLiveReservations(array $productIds): array;

    /**
     * @return LengthAwarePaginator<ProductPurchaseDTO>
     */
    public function findProductPurchases(ProductPurchaseFilterDTO $filter, int $page, int $perPage): LengthAwarePaginator;

    /**
     * @return LazyCollection<int, ProductPurchaseDTO>
     */
    public function getAllProductPurchases(ProductPurchaseFilterDTO $filter): LazyCollection;

    public function getProductPurchaseSummary(ProductPurchaseFilterDTO $filter): ProductPurchaseSummaryDTO;
}
