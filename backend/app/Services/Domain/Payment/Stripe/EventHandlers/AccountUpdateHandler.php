<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\AccountStripePlatformDomainObject;
use HiEvents\DomainObjects\Generated\AccountStripePlatformDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AccountStripePlatformRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\StripeAccountSyncService;
use Stripe\Account;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class AccountUpdateHandler
{
    public function __construct(
        private readonly AccountStripePlatformRepositoryInterface $accountStripePlatformRepository,
        private readonly StripeAccountSyncService                 $stripeAccountSyncService,
    )
    {
    }

    public function handleEvent(Account $stripeAccount): void
    {
        /** @var AccountStripePlatformDomainObject $accountStripePlatform */
        $accountStripePlatform = $this->accountStripePlatformRepository->findFirstWhere([
            AccountStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => $stripeAccount->id,
        ]);

        if ($accountStripePlatform === null) {
            throw new ResourceNotFoundException(
                sprintf('Account stripe platform with stripe account id %s not found', $stripeAccount->id)
            );
        }

        $this->stripeAccountSyncService->syncStripeAccountStatus($accountStripePlatform, $stripeAccount);
    }
}
