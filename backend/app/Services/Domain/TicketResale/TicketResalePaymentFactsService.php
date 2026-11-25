<?php

namespace HiEvents\Services\Domain\TicketResale;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderPaymentPlatformFeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderRefundDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderPaymentPlatformFeeDomainObject;
use HiEvents\DomainObjects\OrderRefundDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\TicketResaleRefundStatus;
use HiEvents\Repository\Interfaces\OrderPaymentPlatformFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResalePaymentFactsDTO;
use HiEvents\Values\MoneyValue;
use Stripe\Refund;

class TicketResalePaymentFactsService
{
    public function __construct(
        private readonly OrderPaymentPlatformFeeRepositoryInterface $platformFeeRepository,
        private readonly OrderRefundRepositoryInterface $orderRefundRepository,
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
    ) {}

    public function forOrder(OrderDomainObject $order, ?OrganizerDomainObject $organizer): TicketResalePaymentFactsDTO
    {
        $payment = $order->getStripePayment();
        $fees = $this->platformFeeRepository->findWhere([OrderPaymentPlatformFeeDomainObjectAbstract::ORDER_ID => $order->getId()]);
        $feeInOrderCurrency = $fees
            ->first(fn (OrderPaymentPlatformFeeDomainObject $fee) => strtoupper($fee->getCurrency()) === strtoupper($order->getCurrency()));

        return new TicketResalePaymentFactsDTO(
            paid_online_through_stripe: $order->isOrderCompleted()
                && $order->getPaymentProvider() === PaymentProviders::STRIPE->value
                && $payment?->getChargeId() !== null
                && ! $order->isBoxOfficeOrder()
                && ! $order->getIsManuallyCreated()
                && ! $order->hasPaymentLink(),
            paid_on_current_stripe_account: $payment !== null
                && $payment->getConnectedAccountId() === $organizer?->getActiveStripeAccountId(),
            payment_fees_recorded: $fees->isNotEmpty(),
            settles_in_order_currency: $feeInOrderCurrency !== null,
            has_only_resale_refunds: $this->hasOnlyResaleRefunds($order),
            card_fee: $feeInOrderCurrency === null
                ? 0
                : MoneyValue::fromFloat($feeInOrderCurrency->getPaymentPlatformFeeAmount(), $order->getCurrency())->toMinorUnit(),
            connected_account_id: $payment?->getConnectedAccountId(),
        );
    }

    private function hasOnlyResaleRefunds(OrderDomainObject $order): bool
    {
        $hasFailedResaleRefund = $this->listingRepository->countWhere([
            TicketResaleListingDomainObjectAbstract::ORDER_ID => $order->getId(),
            TicketResaleListingDomainObjectAbstract::REFUND_STATUS => TicketResaleRefundStatus::FAILED->name,
        ]) > 0;

        if ($hasFailedResaleRefund) {
            return false;
        }

        if ($order->getRefundStatus() === null) {
            return true;
        }

        if ($order->getRefundStatus() !== OrderRefundStatus::PARTIALLY_REFUNDED->name) {
            return false;
        }

        $refunds = $this->orderRefundRepository->findWhere([
            OrderRefundDomainObjectAbstract::ORDER_ID => $order->getId(),
        ]);

        return $refunds->isNotEmpty()
            && $refunds->every(fn (OrderRefundDomainObject $refund) => $this->isSucceededResaleRefund($refund));
    }

    private function isSucceededResaleRefund(OrderRefundDomainObject $refund): bool
    {
        $metadata = $refund->getMetadata();

        return $refund->getStatus() === Refund::STATUS_SUCCEEDED
            && is_array($metadata)
            && isset($metadata[StripePaymentIntentRefundService::RESALE_LISTING_METADATA_KEY]);
    }
}
