<?php

namespace HiEvents\Services\Domain\TicketResale;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\TicketResale\DTO\ResaleDemandDTO;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ResaleClaimService
{
    public function __construct(
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
    ) {}

    /**
     * @param  Collection<int, OrderItemDomainObject>  $orderItems
     * @param  Collection<int, ResaleDemandDTO>  $demand
     *
     * @throws ValidationException
     */
    public function claimForOrder(
        OrderDomainObject $order,
        ?EventSettingDomainObject $eventSettings,
        Collection $orderItems,
        Collection $demand,
    ): OrderDomainObject {
        if ($demand->isEmpty()) {
            return $order;
        }

        $holdEndsAt = Carbon::parse($order->getReservedUntil());

        foreach ($demand as $line) {
            $listings = $this->listingRepository->findClaimableLocked(
                productPriceId: $line->product_price_id,
                eventOccurrenceId: $line->event_occurrence_id,
                unitPrice: $this->lowestUnitPrice($orderItems, $line),
                limit: $line->quantity,
            );

            if ($listings->count() < $line->quantity) {
                throw ValidationException::withMessages([
                    'products' => $this->listingsExistAtFullPrice($line)
                        ? __('The last tickets of this type are being resold by other buyers and can only be bought at full price, so a discount cannot be used for them.')
                        : __('Not enough products available. Please try again.'),
                ]);
            }

            $listings->each(fn (TicketResaleListingDomainObject $listing) => $this->listingRepository->updateFromArray(
                $listing->getId(),
                [TicketResaleListingDomainObjectAbstract::CLAIMED_BY_ORDER_ID => $order->getId()],
            ));

            $holdEndsAt = $holdEndsAt->min($this->cutoff($line->event_occurrence_id, $eventSettings));
        }

        if ($holdEndsAt->gte(Carbon::parse($order->getReservedUntil()))) {
            return $order;
        }

        return $this->orderRepository->updateFromArray($order->getId(), [
            OrderDomainObjectAbstract::RESERVED_UNTIL => $holdEndsAt->toString(),
        ]);
    }

    private function listingsExistAtFullPrice(ResaleDemandDTO $line): bool
    {
        $availableAtFullPrice = $this->listingRepository->countAvailableByPrice(
            [$this->occurrenceRepository->findById($line->event_occurrence_id)->getEventId()],
            $line->event_occurrence_id,
        );

        return ($availableAtFullPrice[$line->product_price_id] ?? 0) >= $line->quantity;
    }

    public function orderHoldsListing(int $orderId): bool
    {
        return $this->listingRepository->countWhere([
            TicketResaleListingDomainObjectAbstract::CLAIMED_BY_ORDER_ID => $orderId,
            TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::LISTED->name,
        ]) > 0;
    }

    /**
     * @throws ResourceConflictException
     */
    public function assertPaidOnlineNow(int $orderId): void
    {
        if ($this->orderHoldsListing($orderId)) {
            throw new ResourceConflictException(
                __('This order includes a resold ticket, so it must be paid by card during checkout.')
            );
        }
    }

    /**
     * @param  Collection<int, OrderItemDomainObject>  $orderItems
     */
    private function lowestUnitPrice(Collection $orderItems, ResaleDemandDTO $line): float
    {
        return (float) $orderItems
            ->filter(fn (OrderItemDomainObject $item) => $item->getProductPriceId() === $line->product_price_id
                && $item->getEventOccurrenceId() === $line->event_occurrence_id)
            ->min(fn (OrderItemDomainObject $item) => $item->getPrice());
    }

    private function cutoff(int $eventOccurrenceId, ?EventSettingDomainObject $eventSettings): Carbon
    {
        return Carbon::parse($this->occurrenceRepository->findById($eventOccurrenceId)->getStartDate())
            ->subMinutes($eventSettings?->getTicketResaleCutoffMinutes() ?? 0);
    }
}
