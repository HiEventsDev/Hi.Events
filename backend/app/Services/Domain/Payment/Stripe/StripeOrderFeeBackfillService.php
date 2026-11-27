<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\Generated\OrderPaymentPlatformFeeDomainObjectAbstract;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderPaymentPlatformFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Stripe\Exception\ApiErrorException;
use Throwable;

class StripeOrderFeeBackfillService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderPaymentPlatformFeeRepositoryInterface $platformFeeRepository,
        private readonly StripePaymentPlatformFeeExtractionService $platformFeeExtractionService,
        private readonly StripeClientFactory $stripeClientFactory,
    ) {}

    /**
     * @throws Throwable
     */
    public function recordIfMissing(int $orderId): void
    {
        $alreadyRecorded = $this->platformFeeRepository->countWhere([
            OrderPaymentPlatformFeeDomainObjectAbstract::ORDER_ID => $orderId,
        ]) > 0;

        if ($alreadyRecorded) {
            return;
        }

        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(new Relationship(StripePaymentDomainObject::class, name: 'stripe_payment'))
            ->findById($orderId);
        $payment = $order->getStripePayment();

        if ($payment?->getChargeId() === null) {
            return;
        }

        $this->platformFeeExtractionService->extractAndStorePlatformFee(
            $order,
            $this->stripeClientFactory
                ->createForPlatform($payment->getStripePlatformEnum())
                ->charges
                ->retrieve(
                    $payment->getChargeId(),
                    ['expand' => ['balance_transaction']],
                    $payment->getConnectedAccountId() === null ? [] : ['stripe_account' => $payment->getConnectedAccountId()],
                ),
            $payment,
        );
    }

    public function recordIfStripeAllows(int $orderId): void
    {
        try {
            $this->recordIfMissing($orderId);
        } catch (ApiErrorException|StripeClientConfigurationException) {
            return;
        }
    }
}
