<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use Brick\Math\Exception\MathException;
use Illuminate\Config\Repository;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\StripeClient;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Values\MoneyValue;

readonly class StripePaymentIntentRefundService
{
    public function __construct(
        private StripeClient $stripeClient,
        private Repository   $config,
    )
    {
    }

    /**
     * @throws ApiErrorException
     * @throws MathException
     * @todo - catch and handle stripe errors
     */
    public function refundPayment(
        MoneyValue                $amount,
        StripePaymentDomainObject $payment,
    ): Refund
    {
        return $this->stripeClient->refunds->create(
            params: [
                'payment_intent' => $payment->getPaymentIntentId(),
                'amount' => $amount->toMinorUnit()
            ],
            opts: $this->getStripeAccountData($payment));
    }

    private function getStripeAccountData(StripePaymentDomainObject $payment): array
    {
        if ($this->config->get('app.saas_mode_enabled')) {
            if ($payment->getConnectedAccountId() === null) {
                throw new RuntimeException(
                    __('Cannot Refund: Stripe connect account not found and saas_mode_enabled is enabled')
                );
            }

            return ['stripe_account' => $payment->getConnectedAccountId()];
        }

        return [];
    }
}
