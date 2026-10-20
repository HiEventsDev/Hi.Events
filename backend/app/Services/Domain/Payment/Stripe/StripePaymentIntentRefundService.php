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
    public const NOTIFY_BUYER_METADATA_KEY = 'notify_buyer';

    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * @throws ApiErrorException
     * @throws MathException
     */
    public function refundPayment(
        MoneyValue $amount,
        MoneyValue $alreadyRefunded,
        int $failedRefundCount,
        StripePaymentDomainObject $payment,
        StripeClient $stripeClient,
        bool $notifyBuyer,
    ): Refund {
        $params = [
            'payment_intent' => $payment->getPaymentIntentId(),
            'amount' => $amount->toMinorUnit(),
        ];

        if ($notifyBuyer) {
            $params['metadata'] = [self::NOTIFY_BUYER_METADATA_KEY => '1'];
        }

        return $stripeClient->refunds->create(
            params: $params,
            opts: array_merge(
                $this->getStripeAccountData($payment),
                ['idempotency_key' => $this->idempotencyKey($payment, $amount, $alreadyRefunded, $failedRefundCount)],
            ),
        );
    }

    private function idempotencyKey(
        StripePaymentDomainObject $payment,
        MoneyValue $amount,
        MoneyValue $alreadyRefunded,
        int $failedRefundCount,
    ): string {
        $key = sprintf('refund_%s_amount_%d', $payment->getPaymentIntentId(), $amount->toMinorUnit());

        if ($alreadyRefunded->toMinorUnit() === 0 && $failedRefundCount === 0) {
            return $key;
        }

        return sprintf('%s_after_%d_failed_%d', $key, $alreadyRefunded->toMinorUnit(), $failedRefundCount);
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
