<?php

namespace HiEvents\Services\Domain\TicketResale;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\ResaleRefundOutcome;
use HiEvents\DomainObjects\Enums\TicketResaleNotice;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderPaymentPlatformFeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrderPaymentPlatformFeeDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\Status\TicketResaleRefundStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Jobs\TicketResale\ProcessResaleRefundJob;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderPaymentPlatformFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\StripeOrderFeeBackfillService;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\TicketResale\DTO\ResaleRefundInputDTO;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Values\MoneyValue;
use Illuminate\Database\DatabaseManager;
use Stripe\Exception\InvalidRequestException;
use Stripe\Refund;
use Throwable;

class ResaleRefundService
{
    private const ATTEMPTS_BEFORE_FETCHING_FEES = 3;

    public function __construct(
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderPaymentPlatformFeeRepositoryInterface $platformFeeRepository,
        private readonly StripePaymentIntentRefundService $stripeRefundService,
        private readonly StripeOrderFeeBackfillService $feeBackfillService,
        private readonly StripeClientFactory $stripeClientFactory,
        private readonly ResaleRefundCalculator $calculator,
        private readonly TransactionLockService $transactionLockService,
        private readonly DatabaseManager $databaseManager,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly TicketResaleNotificationService $notificationService,
    ) {}

    /**
     * @throws Throwable
     */
    public function process(int $listingId, int $attempt): ResaleRefundOutcome
    {
        $listing = $this->listingRepository->findFirst($listingId);

        if ($listing === null || ! $this->isAwaitingRefund($listing)) {
            return ResaleRefundOutcome::SETTLED;
        }

        $sellerOrderShortId = $this->orderRepository->findById($listing->getOrderId())->getShortId();

        if ($attempt >= self::ATTEMPTS_BEFORE_FETCHING_FEES) {
            $this->feeBackfillService->recordIfMissing($listing->getSoldOrderId());
        }

        try {
            $outcome = $this->databaseManager->transaction(function () use ($listingId, $sellerOrderShortId) {
                $this->transactionLockService->lockOrder($sellerOrderShortId);

                return $this->refundUnderLock($listingId);
            });
        } catch (InvalidRequestException) {
            $this->markFailed($listingId);

            return ResaleRefundOutcome::FAILED;
        }

        if ($outcome === ResaleRefundOutcome::NOT_READY) {
            $this->listingRepository->updateFromArray($listingId, [
                TicketResaleListingDomainObjectAbstract::UPDATED_AT => Carbon::now()->toDateTimeString(),
            ]);
        }

        return $outcome;
    }

    public function startRefundsForSoldOrder(int $soldOrderId): void
    {
        $this->dispatchRefundsNotStarted([TicketResaleListingDomainObjectAbstract::SOLD_ORDER_ID => $soldOrderId]);
    }

    public function recordStripeOutcome(Refund $refund): void
    {
        $listingId = $refund->metadata[StripePaymentIntentRefundService::RESALE_LISTING_METADATA_KEY] ?? null;

        if ($listingId === null) {
            return;
        }

        $listing = $this->listingRepository->findFirst((int) $listingId);

        if ($listing === null || $listing->getStatus() !== TicketResaleListingStatus::SOLD->name) {
            return;
        }

        if ($refund->status === Refund::STATUS_SUCCEEDED) {
            $this->orderAuditLogService->logTicketResale(
                OrderAuditAction::TICKET_RESALE_REFUNDED,
                $this->listingRepository->updateFromArray($listing->getId(), [
                    TicketResaleListingDomainObjectAbstract::REFUND_STATUS => TicketResaleRefundStatus::SUCCEEDED->name,
                    TicketResaleListingDomainObjectAbstract::STRIPE_REFUND_ID => $refund->id,
                    TicketResaleListingDomainObjectAbstract::REFUND_AMOUNT => MoneyValue::fromMinorUnit($refund->amount, strtoupper($refund->currency))->toFloat(),
                ]),
            );
        } else {
            $this->markFailed($listing->getId(), $refund->id);
        }

        $this->dispatchRefundsNotStarted([TicketResaleListingDomainObjectAbstract::ORDER_ID => $listing->getOrderId()]);
    }

    private function dispatchRefundsNotStarted(array $scope): void
    {
        $this->listingRepository
            ->findWhere([
                ...$scope,
                TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::SOLD->name,
                TicketResaleListingDomainObjectAbstract::REFUND_STATUS => TicketResaleRefundStatus::PENDING->name,
                TicketResaleListingDomainObjectAbstract::STRIPE_REFUND_ID => null,
            ])
            ->each(fn (TicketResaleListingDomainObject $listing) => ProcessResaleRefundJob::dispatch($listing->getId())->afterCommit());
    }

    public function markFailed(int $listingId, ?string $failedStripeRefundId = null): void
    {
        $listing = $this->listingRepository->findFirst($listingId);

        if ($listing === null || $listing->getStatus() !== TicketResaleListingStatus::SOLD->name) {
            return;
        }

        $isAwaitingOutcome = $listing->getRefundStatus() === TicketResaleRefundStatus::PENDING->name;
        $isReversedByStripe = $failedStripeRefundId !== null
            && $listing->getRefundStatus() === TicketResaleRefundStatus::SUCCEEDED->name
            && $listing->getStripeRefundId() === $failedStripeRefundId;

        if (! $isAwaitingOutcome && ! $isReversedByStripe) {
            return;
        }

        $failedListing = $this->listingRepository->updateFromArray($listing->getId(), [
            TicketResaleListingDomainObjectAbstract::REFUND_STATUS => TicketResaleRefundStatus::FAILED->name,
            TicketResaleListingDomainObjectAbstract::REFUND_AMOUNT => $listing->getRefundAmount() ?? $listing->getEstimatedRefund(),
        ]);

        $this->orderAuditLogService->logTicketResale(OrderAuditAction::TICKET_RESALE_REFUND_FAILED, $failedListing);
        $this->notificationService->notifySeller(
            TicketResaleNotice::REFUND_FAILED_FOR_SELLER,
            $failedListing,
            $failedListing->getRefundAmount(),
        );
        $this->notificationService->notifyOrganizerOfFailedRefund($failedListing);
    }

    /**
     * @throws Throwable
     */
    public function markFailedIfNotStarted(int $listingId): void
    {
        $listing = $this->listingRepository->findFirst($listingId);

        if ($listing === null) {
            return;
        }

        $sellerOrderShortId = $this->orderRepository->findById($listing->getOrderId())->getShortId();

        $this->databaseManager->transaction(function () use ($listingId, $sellerOrderShortId): void {
            $this->transactionLockService->lockOrder($sellerOrderShortId);

            if ($this->listingRepository->findByIdLocked($listingId)?->getStripeRefundId() === null) {
                $this->markFailed($listingId);
            }
        });
    }

    public function fetchStartedRefund(int $listingId): ?Refund
    {
        $listing = $this->listingRepository->findFirst($listingId);

        if ($listing === null
            || $listing->getStripeRefundId() === null
            || $listing->getStatus() !== TicketResaleListingStatus::SOLD->name
            || $listing->getRefundStatus() !== TicketResaleRefundStatus::PENDING->name) {
            return null;
        }

        $payment = $this->orderWithPayment($listing->getOrderId())->getStripePayment();

        if ($payment === null) {
            return null;
        }

        return $this->stripeRefundService->retrieveRefund(
            $listing->getStripeRefundId(),
            $payment,
            $this->stripeClientFactory->createForPlatform($payment->getStripePlatformEnum()),
        );
    }

    /**
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function retryFailed(TicketResaleListingDomainObject $listing): void
    {
        $sellerOrderShortId = $this->orderRepository->findById($listing->getOrderId())->getShortId();

        $this->databaseManager->transaction(function () use ($listing, $sellerOrderShortId): void {
            $this->transactionLockService->lockOrder($sellerOrderShortId);

            $lockedListing = $this->listingRepository->findByIdLocked($listing->getId());

            if ($lockedListing === null || ! $lockedListing->hasFailedRefund()) {
                throw new ResourceConflictException(__('This refund has not failed, so it cannot be retried.'));
            }

            $this->orderAuditLogService->logTicketResale(
                OrderAuditAction::TICKET_RESALE_REFUND_RETRIED,
                $this->listingRepository->updateFromArray($lockedListing->getId(), [
                    TicketResaleListingDomainObjectAbstract::REFUND_STATUS => TicketResaleRefundStatus::PENDING->name,
                    TicketResaleListingDomainObjectAbstract::STRIPE_REFUND_ID => null,
                    TicketResaleListingDomainObjectAbstract::REFUND_ATTEMPTS => $lockedListing->getRefundAttempts() + 1,
                ]),
            );
        });

        ProcessResaleRefundJob::dispatch($listing->getId());
    }

    /**
     * @throws Throwable
     */
    private function refundUnderLock(int $listingId): ResaleRefundOutcome
    {
        $listing = $this->listingRepository->findByIdLocked($listingId);

        if ($listing === null || ! $this->isAwaitingRefund($listing)) {
            return ResaleRefundOutcome::SETTLED;
        }

        $sellerOrder = $this->orderWithPayment($listing->getOrderId());
        $replacementOrder = $this->orderWithPayment($listing->getSoldOrderId());
        $fees = $this->platformFeeRepository->findFirstWhere([
            OrderPaymentPlatformFeeDomainObjectAbstract::ORDER_ID => $replacementOrder->getId(),
        ]);

        if ($fees === null || $sellerOrder->getRefundStatus() === OrderRefundStatus::REFUND_PENDING->name) {
            return ResaleRefundOutcome::NOT_READY;
        }

        if ($sellerOrder->getStripePayment() === null || strtoupper($fees->getCurrency()) !== strtoupper($sellerOrder->getCurrency())) {
            $this->markFailed($listing->getId());

            return ResaleRefundOutcome::FAILED;
        }

        $refund = $this->refundAmount($listing, $sellerOrder, $replacementOrder, $fees);

        if ($refund->toMinorUnit() <= 0) {
            $this->notificationService->notifySeller(
                TicketResaleNotice::SOLD_WITHOUT_REFUND_FOR_SELLER,
                $this->listingRepository->updateFromArray($listing->getId(), [
                    TicketResaleListingDomainObjectAbstract::REFUND_STATUS => TicketResaleRefundStatus::SUCCEEDED->name,
                    TicketResaleListingDomainObjectAbstract::REFUND_AMOUNT => 0,
                ]),
            );

            return ResaleRefundOutcome::SETTLED;
        }

        $this->orderRepository->updateFromArray($sellerOrder->getId(), [
            OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::REFUND_PENDING->name,
        ]);

        $stripeRefund = $this->stripeRefundService->createRefund(
            amount: $refund,
            payment: $sellerOrder->getStripePayment(),
            stripeClient: $this->stripeClientFactory->createForPlatform($sellerOrder->getStripePayment()->getStripePlatformEnum()),
            idempotencyKey: sprintf(
                'resale_refund_%s_%d_%d',
                $sellerOrder->getStripePayment()->getPaymentIntentId(),
                $listing->getId(),
                $listing->getRefundAttempts(),
            ),
            metadata: [StripePaymentIntentRefundService::RESALE_LISTING_METADATA_KEY => (string) $listing->getId()],
        );

        $refundedListing = $this->listingRepository->updateFromArray($listing->getId(), [
            TicketResaleListingDomainObjectAbstract::STRIPE_REFUND_ID => $stripeRefund->id,
            TicketResaleListingDomainObjectAbstract::REFUND_AMOUNT => $refund->toFloat(),
        ]);

        $this->notificationService->notifySeller(TicketResaleNotice::SOLD_FOR_SELLER, $refundedListing, $refundedListing->getRefundAmount());

        return ResaleRefundOutcome::SETTLED;
    }

    private function refundAmount(
        TicketResaleListingDomainObject $listing,
        OrderDomainObject $sellerOrder,
        OrderDomainObject $replacementOrder,
        OrderPaymentPlatformFeeDomainObject $fees,
    ): MoneyValue {
        $currency = $sellerOrder->getCurrency();

        $breakdown = $this->calculator->calculate(new ResaleRefundInputDTO(
            seller_unit_gross: $this->lowestUnitGross($sellerOrder, $listing),
            replacement_unit_gross: $this->lowestUnitGross($replacementOrder, $listing),
            replacement_order_gross: $this->minorUnits($replacementOrder->getTotalGross(), $currency),
            replacement_order_costs: $fees->getTotalFeeMinorUnit(),
            replacement_paid_ticket_count: (int) $replacementOrder->getOrderItems()
                ->filter(fn (OrderItemDomainObject $item) => $item->getProductType() === ProductType::TICKET->name
                    && $item->getTotalGross() > 0)
                ->sum(fn (OrderItemDomainObject $item) => $item->getQuantity()),
            seller_order_unrefunded_balance: $this->minorUnits(
                $sellerOrder->getTotalGross() - $sellerOrder->getTotalRefunded(),
                $currency,
            ),
        ));

        return MoneyValue::fromMinorUnit($breakdown->refund, $currency);
    }

    private function lowestUnitGross(OrderDomainObject $order, TicketResaleListingDomainObject $listing): int
    {
        return (int) $order->getOrderItems()
            ->filter(fn (OrderItemDomainObject $item) => $item->getProductPriceId() === $listing->getProductPriceId()
                && $item->getEventOccurrenceId() === $listing->getEventOccurrenceId()
                && $item->getQuantity() > 0)
            ->min(fn (OrderItemDomainObject $item) => intdiv(
                $this->minorUnits($item->getTotalGross(), $order->getCurrency()),
                $item->getQuantity(),
            ));
    }

    private function minorUnits(float $amount, string $currency): int
    {
        return MoneyValue::fromFloat($amount, $currency)->toMinorUnit();
    }

    private function isAwaitingRefund(TicketResaleListingDomainObject $listing): bool
    {
        return $listing->getStatus() === TicketResaleListingStatus::SOLD->name
            && $listing->getRefundStatus() === TicketResaleRefundStatus::PENDING->name
            && $listing->getStripeRefundId() === null;
    }

    private function orderWithPayment(int $orderId): OrderDomainObject
    {
        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(new Relationship(StripePaymentDomainObject::class, name: 'stripe_payment'))
            ->findById($orderId);
    }
}
