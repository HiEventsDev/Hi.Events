<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use Stripe\PaymentIntent;

readonly class StripePaymentUpdateFromPaymentIntentService
{
    public function __construct(
        private StripePaymentsRepository $stripePaymentsRepository,
    )
    {
    }

    public function updateStripePaymentInfo(PaymentIntent $paymentIntent, StripePaymentDomainObjectAbstract $stripePayment): void
    {
        $this->stripePaymentsRepository->updateWhere(
            attributes: [
                StripePaymentDomainObjectAbstract::LAST_ERROR => $paymentIntent?->last_payment_error?->toArray(),
                StripePaymentDomainObjectAbstract::AMOUNT_RECEIVED => $paymentIntent->amount_received,
                StripePaymentDomainObjectAbstract::PAYMENT_METHOD_ID => is_string($paymentIntent->payment_method)
                    ? $paymentIntent->payment_method
                    : $paymentIntent->payment_method?->id,
                StripePaymentDomainObjectAbstract::CHARGE_ID => is_string($paymentIntent->latest_charge)
                    ? $paymentIntent->latest_charge
                    : $paymentIntent->latest_charge?->id,
            ],
            where: [
                StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->id,
                StripePaymentDomainObjectAbstract::ORDER_ID => $stripePayment->getOrderId(),
            ]);
    }
}
