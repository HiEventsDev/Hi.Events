<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;

class SoldAndReservedQuantitiesService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
    ) {}

    /**
     * @return array<int, int> product_price_id => quantity held by unexpired reservations
     */
    public function getReservedByPrice(int $eventId, ?int $eventOccurrenceId = null): array
    {
        return $this->orderItemRepository->getReservedQuantitiesByPrice($eventId, $eventOccurrenceId);
    }

    public function getReservedTicketsForOccurrence(int $eventOccurrenceId): int
    {
        return $this->orderItemRepository->getReservedTicketQuantityForOccurrence($eventOccurrenceId);
    }

    /**
     * @return array<int, int> product_price_id => quantity sold on that occurrence
     */
    public function getSoldByPriceForOccurrence(int $eventOccurrenceId, ProductType $productType): array
    {
        return $productType === ProductType::TICKET
            ? $this->attendeeRepository->getSoldQuantitiesByPriceForOccurrence($eventOccurrenceId)
            : $this->orderItemRepository->getSoldQuantitiesByPriceForOccurrence($eventOccurrenceId);
    }

    /**
     * @return array<int, int> product_price_id => highest quantity sold on any single occurrence
     */
    public function getMaxSoldOnAnyOccurrenceByPrice(array $productPriceIds, ProductType $productType): array
    {
        return $productType === ProductType::TICKET
            ? $this->attendeeRepository->getMaxSoldPerOccurrenceByPrice($productPriceIds)
            : $this->orderItemRepository->getMaxSoldPerOccurrenceByPrice($productPriceIds);
    }
}
