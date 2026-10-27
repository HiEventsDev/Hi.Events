<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionSyncService;
use Stripe\Exception\ApiErrorException;
use Stripe\Subscription;

class CustomerSubscriptionUpdatedHandler
{
    public function __construct(
        private readonly AccountSubscriptionSyncService $accountSubscriptionSyncService,
    ) {}

    /**
     * @throws ApiErrorException
     * @throws StripeClientConfigurationException
     */
    public function handleEvent(Subscription $subscription, ?string $connectedAccountId): void
    {
        if ($connectedAccountId !== null) {
            return;
        }

        $this->accountSubscriptionSyncService->syncCustomer((string) $subscription->customer);
    }
}
