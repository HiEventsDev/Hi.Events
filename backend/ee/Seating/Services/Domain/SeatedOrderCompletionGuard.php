<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\Constants;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\SeatClaimDomainObject;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use Illuminate\Support\Collection;

class SeatedOrderCompletionGuard
{
    public function __construct(
        private readonly SeatClaimService $seatClaimService,
        private readonly SeatClaimRepositoryInterface $seatClaimRepository,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
        private readonly AvailableProductQuantitiesFetchService $availableProductQuantitiesFetchService,
        private readonly SeatedProductLookupService $seatedProductLookup,
        private readonly SeatingEventLockService $seatingEventLock,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function assertSeatsHeld(OrderDomainObject $order): void
    {
        if (! $this->eventSeatMapLookup->existsForEvent($order->getEventId())) {
            return;
        }

        $this->seatingEventLock->lock($order->getEventId());

        $seatedItems = $this->seatedItems($order);
        if ($seatedItems->isEmpty()) {
            return;
        }

        $eventSeatMap = $this->eventSeatMapLookup->findForEvent($order->getEventId());
        $claims = $this->seatClaimService->claimsForOrder($order->getId());

        if ($eventSeatMap === null || ! $this->claimsCoverItems($claims, $seatedItems)) {
            throw new ResourceConflictException(__('Your seats are no longer held. Please restart your order.'));
        }

        $index = $this->eventSeatMapLookup->indexFor($order->getEventId());
        $seatsUnchanged = $claims->every(fn (SeatClaimDomainObject $claim) => $index->has($claim->getSeatUid())
            && $index->bandOf($claim->getSeatUid()) === $claim->getBandKey());

        if (! $seatsUnchanged || ! $this->zonesStillHaveRoom($order, $claims, $index)) {
            throw new ResourceConflictException(__('Your seats are no longer held. Please restart your order.'));
        }
    }

    public function canRescueExpired(OrderDomainObject $order): bool
    {
        if ($this->seatedItems($order)->isEmpty() || $this->seatClaimService->claimsForOrder($order->getId())->isEmpty()) {
            return false;
        }

        try {
            $this->assertSeatsHeld($order);
        } catch (ResourceConflictException) {
            return false;
        }

        return $this->itemsAreStillAvailable($order);
    }

    /**
     * @return Collection<int, OrderItemDomainObject>
     */
    private function seatedItems(OrderDomainObject $order): Collection
    {
        return ($order->getOrderItems() ?? collect())
            ->filter(fn (OrderItemDomainObject $item) => $this->seatedProductLookup->isSeated($item->getProductId()))
            ->values();
    }

    /**
     * @param  Collection<int, SeatClaimDomainObject>  $claims
     * @param  Collection<int, OrderItemDomainObject>  $seatedItems
     */
    private function claimsCoverItems(Collection $claims, Collection $seatedItems): bool
    {
        $claimCountByItem = $claims->countBy(fn (SeatClaimDomainObject $claim) => (int) $claim->getOrderItemId());

        return $claims->count() === $seatedItems->sum(fn (OrderItemDomainObject $item) => $item->getQuantity())
            && $seatedItems->every(fn (OrderItemDomainObject $item) => $claimCountByItem->get($item->getId(), 0) === $item->getQuantity());
    }

    /**
     * @param  Collection<int, SeatClaimDomainObject>  $claims
     */
    private function zonesStillHaveRoom(OrderDomainObject $order, Collection $claims, SeatMapIndex $index): bool
    {
        return $claims
            ->filter(fn (SeatClaimDomainObject $claim) => $claim->getIsZone())
            ->groupBy(fn (SeatClaimDomainObject $claim) => $claim->getEventOccurrenceId().':'.$claim->getSeatUid())
            ->every(fn (Collection $zoneClaims) => $this->seatClaimRepository->countLiveForZone(
                $zoneClaims->first()->getEventOccurrenceId(),
                $zoneClaims->first()->getSeatUid(),
                $order->getId(),
            ) + $zoneClaims->count() <= $index->zoneCapacity($zoneClaims->first()->getSeatUid()));
    }

    private function itemsAreStillAvailable(OrderDomainObject $order): bool
    {
        return $order->getOrderItems()
            ->groupBy(fn (OrderItemDomainObject $item) => (int) $item->getEventOccurrenceId())
            ->every(function (Collection $occurrenceItems) use ($order) {
                $availability = $this->availableProductQuantitiesFetchService->getAvailableProductQuantities(
                    $order->getEventId(),
                    ignoreCache: true,
                    eventOccurrenceId: $occurrenceItems->first()->getEventOccurrenceId(),
                );

                return $this->pricesHaveRoom($availability, $occurrenceItems)
                    && $availability->firstOverflowingPool($this->quantityByProduct($occurrenceItems)) === null
                    && $this->occurrenceHasRoom($availability, $occurrenceItems);
            });
    }

    /**
     * @param  Collection<int, OrderItemDomainObject>  $items
     */
    private function pricesHaveRoom(AvailableProductQuantitiesResponseDTO $availability, Collection $items): bool
    {
        return $items
            ->groupBy(fn (OrderItemDomainObject $item) => $item->getProductPriceId())
            ->every(function (Collection $priceItems, int $priceId) use ($availability) {
                $available = $availability->getAvailableQuantityForPrice($priceId);

                return $available === Constants::INFINITE
                    || $priceItems->sum(fn (OrderItemDomainObject $item) => $item->getQuantity()) <= $available;
            });
    }

    /**
     * @param  Collection<int, OrderItemDomainObject>  $items
     */
    private function occurrenceHasRoom(AvailableProductQuantitiesResponseDTO $availability, Collection $items): bool
    {
        $remaining = $availability->remainingOccurrenceCapacity();
        $ticketProductIds = $availability->productQuantities
            ->where('product_type', ProductType::TICKET->name)
            ->pluck('product_id')
            ->unique()
            ->all();

        return $remaining === null || $items
            ->filter(fn (OrderItemDomainObject $item) => in_array($item->getProductId(), $ticketProductIds, true))
            ->sum(fn (OrderItemDomainObject $item) => $item->getQuantity()) <= $remaining;
    }

    /**
     * @param  Collection<int, OrderItemDomainObject>  $items
     * @return array<int, int>
     */
    private function quantityByProduct(Collection $items): array
    {
        return $items
            ->groupBy(fn (OrderItemDomainObject $item) => $item->getProductId())
            ->map(fn (Collection $productItems) => $productItems->sum(fn (OrderItemDomainObject $item) => $item->getQuantity()))
            ->all();
    }
}
