<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\Services\Domain\Payment\Stripe\StripeAccountSyncService;
use Stripe\Account;

class AccountUpdateHandler
{
    public function __construct(
        private readonly StripeAccountSyncService $stripeAccountSyncService,
    )
    {
    }

    public function handleEvent(Account $stripeAccount): void
    {
        $this->stripeAccountSyncService->syncStripeAccountStatusByAccountId($stripeAccount);
    }
}
