<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;

class SoldAndReservedQuantitiesService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly TicketResaleListingRepositoryInterface $resaleListingRepository,
    ) {}

    /**
     * @param  int[]  $eventIds
     * @return array<int, int> product_price_id => unsold stock held by unexpired reservations
     */
    public function getReservedByPrice(array $eventIds, ?int $eventOccurrenceId = null): array
    {
        $reserved = $this->orderItemRepository->getReservedQuantitiesByPrice($eventIds, $eventOccurrenceId);

        foreach ($this->resaleListingRepository->countLiveClaimsByPrice($eventIds, $eventOccurrenceId) as $priceId => $heldListings) {
            if (isset($reserved[$priceId])) {
                $reserved[$priceId] = max(0, $reserved[$priceId] - $heldListings);
            }
        }

        return $reserved;
    }

    public function getReservedTicketsForOccurrence(int $eventOccurrenceId): int
    {
        return max(
            0,
            $this->orderItemRepository->getReservedTicketQuantityForOccurrence($eventOccurrenceId)
                - $this->resaleListingRepository->countLiveClaimsForOccurrence($eventOccurrenceId),
        );
    }

    /**
     * @param  int[]  $eventIds
     * @return array<int, int> product_price_id => listed tickets a new order could take
     */
    public function getResaleStockByPrice(array $eventIds, ?int $eventOccurrenceId = null): array
    {
        return $this->resaleListingRepository->countAvailableByPrice($eventIds, $eventOccurrenceId);
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
