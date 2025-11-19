<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\AccountStripePlatformDomainObject;
use HiEvents\DomainObjects\Generated\AccountStripePlatformDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\AccountVatSettingDomainObjectAbstract;
use HiEvents\Helper\Url;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountStripePlatformRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use Illuminate\Config\Repository;
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
        private readonly AccountVatSettingRepositoryInterface     $vatSettingRepository,
        private readonly Repository                               $config,
    )
    {
    }

    /**
     * Sync Stripe account status and details to our database
     */
    public function syncStripeAccountStatus(
        AccountStripePlatformDomainObject $accountStripePlatform,
        Account                           $stripeAccount
    ): void
    {
        $isAccountSetupCompleted = $this->isStripeAccountComplete($stripeAccount);
        $isCurrentlyComplete = $accountStripePlatform->getStripeSetupCompletedAt() !== null;

        // Only update if status has actually changed
        if ($isCurrentlyComplete === $isAccountSetupCompleted) {
            // Still update account details even if status hasn't changed
            $this->updateAccountDetails($stripeAccount);
            return;
        }

        if ($isAccountSetupCompleted) {
            $this->markAccountAsComplete($accountStripePlatform, $stripeAccount);
        } else {
            $this->logger->info(sprintf(
                'Stripe Connect account is no longer complete. Updating account stripe platform %s',
                $stripeAccount->id
            ));
            $this->updateAccountStatusAndDetails($stripeAccount, isAccountSetupCompleted: false);
            $this->updateAccountDetails($stripeAccount);
        }
    }

    /**
     * Force update account status when we know it should be complete
     * (e.g., from GetStripeConnectAccountsHandler when Stripe says complete but DB doesn't)
     */
    public function markAccountAsComplete(
        AccountStripePlatformDomainObject $accountStripePlatform,
        Account                           $stripeAccount
    ): void
    {
        $this->logger->info(sprintf(
            'Marking Stripe Connect account as complete for account stripe platform %s with Stripe account ID %s',
            $accountStripePlatform->getId(),
            $stripeAccount->id
        ));

        $this->updateAccountStatusAndDetails($stripeAccount, isAccountSetupCompleted: true);
        $this->updateAccountCountryAndVerificationStatus($accountStripePlatform, $stripeAccount);
        $this->createVatSettingIfMissing($accountStripePlatform);
    }

    public function isStripeAccountComplete(Account $stripeAccount): bool
    {
        return $stripeAccount->charges_enabled && $stripeAccount->payouts_enabled;
    }

    private function updateAccountStatusAndDetails(
        Account $stripeAccount,
        bool    $isAccountSetupCompleted
    ): void
    {
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

    private function updateAccountDetails(Account $stripeAccount): void
    {
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

    private function updateAccountCountryAndVerificationStatus(
        AccountStripePlatformDomainObject $accountStripePlatform,
        Account                           $stripeAccount,
    ): void
    {
        $account = $this->accountRepository->findById($accountStripePlatform->getAccountId());

        $updates = [];
        if (!$account->getCountry()) {
            $updates['country'] = strtoupper($stripeAccount->country);
        }

        if (!$account->getIsManuallyVerified()) {
            $updates['is_manually_verified'] = true;
        }

        if (!empty($updates)) {
            $this->accountRepository->updateWhere(
                attributes: $updates,
                where: [
                    'id' => $accountStripePlatform->getAccountId(),
                ]
            );
        }
    }

    private function createVatSettingIfMissing(AccountStripePlatformDomainObject $accountStripePlatform): void
    {
        if ($this->config->get('app.tax.eu_vat_handling_enabled') !== true) {
            $this->logger->info('EU VAT handling is disabled, skipping VAT setting creation.', [
                'account_stripe_platform_id' => $accountStripePlatform->getId(),
                'account_id' => $accountStripePlatform->getAccountId(),
            ]);
            return;
        }

        $existingVatSetting = $this->vatSettingRepository->findFirstWhere([
            AccountVatSettingDomainObjectAbstract::ACCOUNT_ID => $accountStripePlatform->getAccountId(),
        ]);

        if ($existingVatSetting === null) {
            $this->vatSettingRepository->create([
                AccountVatSettingDomainObjectAbstract::ACCOUNT_ID => $accountStripePlatform->getAccountId(),
                AccountVatSettingDomainObjectAbstract::VAT_VALIDATED => false,
                AccountVatSettingDomainObjectAbstract::VAT_COUNTRY_CODE => $accountStripePlatform
                        ->getStripeAccountDetails()['country'] ?? null,
            ]);
        }
    }
}
