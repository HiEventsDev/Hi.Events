<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use Stripe\StripeClient;

readonly class CreateStripeConnectAccountService
{
    public function __construct(
        private StripeClient $stripe,
    )
    {
    }

    public function execute(): string
    {
        try {
            return $this->createStripeConnectAccount();
        } catch (Throwable $e) {
            throw new CreateStripeConnectAccountFailedException($e->getMessage());
        }
        $stripeAccount = $this->stripe->accounts->create([
            'type' => 'express',
        ]);

        return $stripeAccount->id;
    }
}
