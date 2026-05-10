<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrderItemDomainObject;

/**
 * @extends RepositoryInterface<OrderItemDomainObject>
 */
interface OrderItemRepositoryInterface extends RepositoryInterface
{
    /**
     * Returns the total quantity reserved (orders in RESERVED status, still within their
     * reservation window) against a specific event occurrence. Used by checkout capacity
     * validation to subtract pending reservations from the available pool.
     */
    public function getReservedQuantityForOccurrence(int $occurrenceId): int;
}
