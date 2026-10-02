<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrderItemDomainObject;

/**
 * @extends RepositoryInterface<OrderItemDomainObject>
 */
interface OrderItemRepositoryInterface extends RepositoryInterface
{
    public function getReservedTicketQuantityForOccurrence(int $occurrenceId): int;

    /**
     * @return array<int, int> product_price_id => quantity
     */
    public function getReservedQuantitiesByPrice(int $eventId, ?int $occurrenceId = null): array;

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
}
