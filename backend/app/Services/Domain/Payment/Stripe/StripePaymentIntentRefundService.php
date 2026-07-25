<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use Brick\Math\Exception\MathException;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\StripeClient;

class StripePaymentIntentRefundService
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * @throws ApiErrorException
     * @throws MathException
     *
     * @todo - catch and handle stripe errors
     */
    public function refundPayment(
        MoneyValue $amount,
        StripePaymentDomainObject $payment,
        StripeClient $stripeClient,
    ): Refund {
        $opts = array_merge(
            $this->getStripeAccountData($payment),
            [
                'idempotency_key' => sprintf(
                    'refund_%s_amount_%d',
                    $payment->getPaymentIntentId(),
                    $amount->toMinorUnit(),
                ),
            ],
        );

        return $stripeClient->refunds->create(
            params: [
                'payment_intent' => $payment->getPaymentIntentId(),
                'amount' => $amount->toMinorUnit(),
            ],
            opts: $opts,
        );
    }

    private function getStripeAccountData(StripePaymentDomainObject $payment): array
    {
        if ($this->config->get('app.saas_mode_enabled')) {
            if ($payment->getConnectedAccountId() === null) {
                throw new RuntimeException(
                    __('Cannot Refund: Stripe connect account not found and saas_mode_enabled is enabled')
                );
            }

            return [
                'stripe_account' => $payment->getConnectedAccountId(),
            ];
        }

        return [];
    }
}
