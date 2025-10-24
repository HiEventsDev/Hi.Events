<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\AccountStripePlatformDomainObject;
use HiEvents\DomainObjects\Generated\AccountStripePlatformDomainObjectAbstract;
use HiEvents\Helper\Url;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountStripePlatformRepositoryInterface;
use Psr\Log\LoggerInterface;
use Stripe\Account;
use Stripe\StripeClient;
use Throwable;

class StripeAccountSyncService
{
    public function __construct(
        private readonly LoggerInterface                          $logger,
        private readonly AccountRepositoryInterface               $accountRepository,
        private readonly AccountStripePlatformRepositoryInterface $accountStripePlatformRepository,
    )
    {
    }

    /**
     * Sync Stripe account status and details to our database
     */
    public function syncStripeAccountStatus(
        AccountStripePlatformDomainObject $accountStripePlatform,
        Account $stripeAccount
    ): void {
        $isAccountSetupCompleted = $this->isStripeAccountComplete($stripeAccount);
        $isCurrentlyComplete = $accountStripePlatform->getStripeSetupCompletedAt() !== null;

        // Only update if status has actually changed
        if ($isCurrentlyComplete === $isAccountSetupCompleted) {
            // Still update account details even if status hasn't changed
            $this->updateAccountDetails($accountStripePlatform, $stripeAccount);
            return;
        }

        $this->logger->info(sprintf(
            'Stripe Connect account status change. Updating account stripe platform %s with stripe account setup completed %s',
            $stripeAccount->id,
            $isAccountSetupCompleted ? 'true' : 'false'
        ));

        $this->updateAccountStatusAndDetails($accountStripePlatform, $stripeAccount, $isAccountSetupCompleted);

        // Also update account verification status if setup is complete
        if ($isAccountSetupCompleted) {
            $this->updateAccountVerificationStatus($accountStripePlatform);
        }
    }

    /**
     * Force update account status when we know it should be complete
     * (e.g., from GetStripeConnectAccountsHandler when Stripe says complete but DB doesn't)
     */
    public function markAccountAsComplete(
        AccountStripePlatformDomainObject $accountStripePlatform,
        Account $stripeAccount
    ): void {
        $this->logger->info(sprintf(
            'Marking Stripe Connect account as complete for account stripe platform %s with Stripe account ID %s',
            $accountStripePlatform->getId(),
            $stripeAccount->id
        ));

        $this->updateAccountStatusAndDetails($accountStripePlatform, $stripeAccount, true);
        $this->updateAccountVerificationStatus($accountStripePlatform);
    }

    public function isStripeAccountComplete(Account $stripeAccount): bool
    {
        return $stripeAccount->charges_enabled && $stripeAccount->payouts_enabled;
    }

    private function updateAccountStatusAndDetails(
        AccountStripePlatformDomainObject $accountStripePlatform,
        Account $stripeAccount,
        bool $isAccountSetupCompleted
    ): void {
        $this->accountStripePlatformRepository->updateWhere(
            attributes: [
                AccountStripePlatformDomainObjectAbstract::STRIPE_SETUP_COMPLETED_AT => $isAccountSetupCompleted ? now() : null,
                AccountStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_DETAILS => $this->buildAccountDetails($stripeAccount),
            ],
            where: [
                AccountStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => $stripeAccount->id,
            ]
        );
    }

    private function updateAccountDetails(
        AccountStripePlatformDomainObject $accountStripePlatform,
        Account $stripeAccount
    ): void {
        $this->accountStripePlatformRepository->updateWhere(
            attributes: [
                AccountStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_DETAILS => $this->buildAccountDetails($stripeAccount),
            ],
            where: [
                AccountStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => $stripeAccount->id,
            ]
        );
    }

    private function buildAccountDetails(Account $stripeAccount): string
    {
        return json_encode([
            'charges_enabled' => $stripeAccount->charges_enabled,
            'payouts_enabled' => $stripeAccount->payouts_enabled,
            'country' => $stripeAccount->country,
            'capabilities' => is_array($stripeAccount->capabilities)
                ? $stripeAccount->capabilities
                : ($stripeAccount->capabilities && method_exists($stripeAccount->capabilities, 'toArray')
                    ? $stripeAccount->capabilities->toArray()
                    : null),
            'type' => $stripeAccount->type,
            'business_type' => $stripeAccount->business_type,
            'requirements' => [
                'currently_due' => $stripeAccount->requirements?->currently_due ?? [],
                'eventually_due' => $stripeAccount->requirements?->eventually_due ?? [],
                'past_due' => $stripeAccount->requirements?->past_due ?? [],
                'pending_verification' => $stripeAccount->requirements?->pending_verification ?? [],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    public function createStripeAccountSetupUrl(Account $stripeAccount, StripeClient $stripeClient): ?string
    {
        try {
            $accountLink = $stripeClient->accountLinks->create([
                'account' => $stripeAccount->id,
                'refresh_url' => Url::getFrontEndUrlFromConfig(Url::STRIPE_CONNECT_REFRESH_URL, [
                    'is_refresh' => true,
                ]),
                'return_url' => Url::getFrontEndUrlFromConfig(Url::STRIPE_CONNECT_RETURN_URL, [
                    'is_return' => true,
                ]),
                'type' => 'account_onboarding',
            ]);

            return $accountLink->url;
        } catch (Throwable $e) {
            $this->logger->error('Failed to create Stripe Connect Account Link', [
                'stripe_account_id' => $stripeAccount->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function updateAccountVerificationStatus(AccountStripePlatformDomainObject $accountStripePlatform): void
    {
        $account = $this->accountRepository->findById($accountStripePlatform->getAccountId());
        if (!$account->getIsManuallyVerified()) {
            $this->accountRepository->updateWhere(
                attributes: [
                    'is_manually_verified' => true,
                ],
                where: [
                    'id' => $accountStripePlatform->getAccountId(),
                ]
            );
        }
    }
}
