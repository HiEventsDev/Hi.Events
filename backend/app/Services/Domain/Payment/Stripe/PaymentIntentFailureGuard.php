<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;

class PaymentIntentFailureGuard
{
    public const MAX_FAILED_ATTEMPTS = 5;

    private const FAILURE_WINDOW_SECONDS = 86400;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly StripeClientFactory $stripeClientFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function recordFailure(StripePaymentDomainObject $stripePayment): void
    {
        $key = 'stripe-payment-intent-failures:'.$stripePayment->getPaymentIntentId();

        $this->cache->add($key, 0, self::FAILURE_WINDOW_SECONDS);
        $failures = (int) $this->cache->increment($key);

        if ($failures !== self::MAX_FAILED_ATTEMPTS) {
            return;
        }

        try {
            $this->stripeClientFactory
                ->createForPlatform($stripePayment->getStripePlatformEnum())
                ->paymentIntents
                ->cancel(
                    $stripePayment->getPaymentIntentId(),
                    [],
                    $stripePayment->getConnectedAccountId() !== null
                        ? ['stripe_account' => $stripePayment->getConnectedAccountId()]
                        : [],
                );

            $this->logger->warning('Cancelled payment intent after repeated failed payment attempts', [
                'payment_intent_id' => $stripePayment->getPaymentIntentId(),
                'order_id' => $stripePayment->getOrderId(),
                'failed_attempts' => $failures,
            ]);
        } catch (ApiErrorException $exception) {
            $this->logger->error('Could not cancel payment intent after repeated failed payment attempts', [
                'payment_intent_id' => $stripePayment->getPaymentIntentId(),
                'order_id' => $stripePayment->getOrderId(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
