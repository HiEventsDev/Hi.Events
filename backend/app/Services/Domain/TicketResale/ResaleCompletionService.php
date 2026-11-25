<?php

namespace HiEvents\Services\Domain\TicketResale;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Enums\TicketResaleCloseReason;
use HiEvents\DomainObjects\Enums\TicketResaleNotice;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\Status\TicketResaleRefundStatus;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCancellationService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Support\Collection;
use Throwable;

class ResaleCompletionService
{
    public function __construct(
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductQuantityUpdateService $productQuantityService,
        private readonly EventStatisticsCancellationService $statisticsCancellationService,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly TransactionLockService $transactionLockService,
        private readonly TicketResaleNotificationService $notificationService,
    ) {}

    /**
     * @return Collection<int, TicketResaleListingDomainObject> listings sold to the paying order
     *
     * @throws Throwable
     */
    public function lockEventIfHoldingListings(OrderDomainObject $buyerOrder): void
    {
        if ($this->holdsListings($buyerOrder)) {
            $this->transactionLockService->lockEvent($buyerOrder->getEventId());
        }
    }

    public function hasUnsellableClaim(OrderDomainObject $buyerOrder): bool
    {
        return $this->listingRepository->countUnsellableClaimsForOrder($buyerOrder->getId()) > 0;
    }

    public function completeForPaidOrder(OrderDomainObject $buyerOrder): Collection
    {
        if (! $this->holdsListings($buyerOrder)) {
            return collect();
        }

        $this->transactionLockService->lockEvent($buyerOrder->getEventId());

        $listings = $this->listingRepository->findListedClaimedByOrderLocked($buyerOrder->getId());

        $sellerOrders = $this->orderRepository
            ->findWhereIn('id', $listings->map(fn (TicketResaleListingDomainObject $listing) => $listing->getOrderId())->unique()->values()->all())
            ->sortBy(fn (OrderDomainObject $order) => $order->getId())
            ->keyBy(fn (OrderDomainObject $order) => $order->getId());

        $sellerOrders->each(fn (OrderDomainObject $order) => $this->transactionLockService->lockOrder($order->getShortId()));

        return $listings
            ->map(fn (TicketResaleListingDomainObject $listing) => $this->complete(
                $listing,
                $buyerOrder,
                $sellerOrders->get($listing->getOrderId()),
            ))
            ->filter()
            ->values();
    }

    /**
     * @throws Throwable
     */
    private function complete(
        TicketResaleListingDomainObject $listing,
        OrderDomainObject $buyerOrder,
        OrderDomainObject $sellerOrder,
    ): ?TicketResaleListingDomainObject {
        $sellerAttendee = $this->attendeeRepository->findByIdLocked($listing->getAttendeeId());

        if ($sellerAttendee->getStatus() !== AttendeeStatus::ACTIVE->name) {
            $this->listingRepository->closeIfListed($listing->getId(), TicketResaleCloseReason::TICKET_CANCELLED->value);

            return null;
        }

        $this->voidSellerTicket($sellerAttendee, $sellerOrder);

        $soldListing = $this->listingRepository->updateFromArray($listing->getId(), [
            TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::SOLD->name,
            TicketResaleListingDomainObjectAbstract::SOLD_ORDER_ID => $buyerOrder->getId(),
            TicketResaleListingDomainObjectAbstract::REFUND_STATUS => TicketResaleRefundStatus::PENDING->name,
        ]);

        $this->orderAuditLogService->logTicketResale(OrderAuditAction::TICKET_RESALE_SOLD, $soldListing);
        $this->notificationService->notifyHolder(TicketResaleNotice::SOLD_FOR_HOLDER, $soldListing);

        return $soldListing;
    }

    /**
     * @throws Throwable
     */
    private function voidSellerTicket(AttendeeDomainObject $sellerAttendee, OrderDomainObject $sellerOrder): void
    {
        $this->attendeeRepository->updateFromArray($sellerAttendee->getId(), [
            AttendeeDomainObjectAbstract::STATUS => AttendeeStatus::CANCELLED->name,
        ]);

        $this->productQuantityService->decreaseQuantitySold(
            $sellerAttendee->getProductPriceId(),
            1,
            $sellerAttendee->getEventOccurrenceId(),
        );

        $this->statisticsCancellationService->decrementForCancelledAttendee(
            eventId: $sellerAttendee->getEventId(),
            orderDate: $sellerOrder->getCreatedAt(),
            occurrenceId: $sellerAttendee->getEventOccurrenceId(),
        );
    }

    private function holdsListings(OrderDomainObject $buyerOrder): bool
    {
        return $this->listingRepository->countWhere([
            TicketResaleListingDomainObjectAbstract::CLAIMED_BY_ORDER_ID => $buyerOrder->getId(),
            TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::LISTED->name,
        ]) > 0;
    }
}
