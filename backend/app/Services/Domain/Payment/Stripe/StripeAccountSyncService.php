<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\Enums\CountryCode;
use HiEvents\DomainObjects\Generated\OrganizerStripePlatformDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrganizerVatSettingDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerVatSettingRepositoryInterface;
use HiEvents\Services\Domain\Organizer\AssignCurrencyDefaultOrganizerConfigurationService;
use Illuminate\Config\Repository;
use Psr\Log\LoggerInterface;
use Stripe\Account;
use Stripe\StripeClient;
use Throwable;

class StripeAccountSyncService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly OrganizerStripePlatformRepositoryInterface $organizerStripePlatformRepository,
        private readonly OrganizerVatSettingRepositoryInterface $vatSettingRepository,
        private readonly Repository $config,
        private readonly AssignCurrencyDefaultOrganizerConfigurationService $assignCurrencyDefaultOrganizerConfigurationService,
    ) {}

    public function isStripeAccountComplete(Account $stripeAccount): bool
    {
        return $stripeAccount->charges_enabled && $stripeAccount->payouts_enabled;
    }

    public function createStripeAccountSetupUrl(Account $stripeAccount, StripeClient $stripeClient, int $organizerId): ?string
    {
        try {
            $refreshUrl = sprintf(Url::getFrontEndUrlFromConfig(Url::STRIPE_CONNECT_REFRESH_URL), $organizerId);
            $returnUrl = sprintf(Url::getFrontEndUrlFromConfig(Url::STRIPE_CONNECT_RETURN_URL), $organizerId);

            $accountLink = $stripeClient->accountLinks->create([
                'account' => $stripeAccount->id,
                'refresh_url' => $this->appendQueryParam($refreshUrl, 'is_refresh=1'),
                'return_url' => $this->appendQueryParam($returnUrl, 'is_return=1'),
                'type' => 'account_onboarding',
            ]);

            return $accountLink->url;
        } catch (Throwable $e) {
            $this->logger->error('Failed to create Stripe Connect Account Link', [
                'stripe_account_id' => $stripeAccount->id,
                'organizer_id' => $organizerId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function appendQueryParam(string $url, string $param): string
    {
        $hashPosition = strpos($url, '#');
        $base = $hashPosition === false ? $url : substr($url, 0, $hashPosition);
        $fragment = $hashPosition === false ? '' : substr($url, $hashPosition);
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.$param.$fragment;
    }

    public function syncStripeAccountStatusByAccountId(Account $stripeAccount): void
    {
        $details = $this->buildAccountDetails($stripeAccount);
        $isAccountSetupCompleted = $this->isStripeAccountComplete($stripeAccount);

        $this->organizerStripePlatformRepository->updateWhere(
            attributes: [
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_SETUP_COMPLETED_AT => $isAccountSetupCompleted ? now() : null,
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_DETAILS => $details,
            ],
            where: [
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => $stripeAccount->id,
            ]
        );

        if (! $isAccountSetupCompleted) {
            return;
        }

        $organizerRows = $this->organizerStripePlatformRepository->findWhere([
            OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => $stripeAccount->id,
        ]);

        foreach ($organizerRows as $organizerRow) {
            $this->updateOrganizerCountryAndVerificationStatus($organizerRow, $stripeAccount);
            $this->seedVatSettingForOrganizerIfMissing(
                organizerId: $organizerRow->getOrganizerId(),
                countryCode: $stripeAccount->country,
                stripeAccountId: $stripeAccount->id,
                organizerStripePlatformId: $organizerRow->getId(),
            );
            $this->assignCurrencyDefaultOrganizerConfigurationService->assignForCountry(
                organizerId: $organizerRow->getOrganizerId(),
                countryCode: $stripeAccount->country,
            );
        }
    }

    public function markAccountAsCompleteForOrganizer(
        OrganizerStripePlatformDomainObject $organizerStripePlatform,
        Account $stripeAccount,
    ): void {
        $this->logger->info(sprintf(
            'Marking Stripe Connect account as complete for organizer stripe platform %s with Stripe account ID %s',
            $organizerStripePlatform->getId(),
            $stripeAccount->id,
        ));

        $this->organizerStripePlatformRepository->updateWhere(
            attributes: [
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_SETUP_COMPLETED_AT => now(),
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_DETAILS => $this->buildAccountDetails($stripeAccount),
            ],
            where: [
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => $stripeAccount->id,
            ]
        );

        $this->updateOrganizerCountryAndVerificationStatus($organizerStripePlatform, $stripeAccount);
        $this->seedVatSettingForOrganizerIfMissing(
            organizerId: $organizerStripePlatform->getOrganizerId(),
            countryCode: $stripeAccount->country,
            stripeAccountId: $stripeAccount->id,
            organizerStripePlatformId: $organizerStripePlatform->getId(),
        );
        $this->assignCurrencyDefaultOrganizerConfigurationService->assignForCountry(
            organizerId: $organizerStripePlatform->getOrganizerId(),
            countryCode: $stripeAccount->country,
        );
    }

    public function seedVatSettingForOrganizerIfMissing(
        int $organizerId,
        ?string $countryCode,
        ?string $stripeAccountId = null,
        ?int $organizerStripePlatformId = null,
    ): void {
        if (! $this->config->get('app.saas_mode_enabled')) {
            return;
        }

        if ($this->config->get('app.tax.eu_vat_handling_enabled') !== true) {
            return;
        }

        if (! $countryCode) {
            $this->logger->error('Stripe account country code is missing, cannot create VAT setting.', [
                'organizer_id' => $organizerId,
                'organizer_stripe_platform_id' => $organizerStripePlatformId,
                'stripe_account_id' => $stripeAccountId,
            ]);

            return;
        }

        $countryCode = strtoupper($countryCode);
        if (! CountryCode::isEuCountry(CountryCode::from($countryCode))) {
            return;
        }

        $existingVatSetting = $this->vatSettingRepository->findByOrganizerId($organizerId);

        if ($existingVatSetting === null) {
            $this->vatSettingRepository->create([
                OrganizerVatSettingDomainObjectAbstract::ORGANIZER_ID => $organizerId,
                OrganizerVatSettingDomainObjectAbstract::VAT_VALIDATED => false,
                OrganizerVatSettingDomainObjectAbstract::VAT_COUNTRY_CODE => $countryCode,
            ]);
        }
    }

    public function syncStripeAccountDetailsForOrganizer(
        OrganizerStripePlatformDomainObject $organizerStripePlatform,
        Account $stripeAccount,
    ): void {
        $this->organizerStripePlatformRepository->updateWhere(
            attributes: [
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_DETAILS => $this->buildAccountDetails($stripeAccount),
            ],
            where: [
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => $stripeAccount->id,
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

    private function updateOrganizerCountryAndVerificationStatus(
        OrganizerStripePlatformDomainObject $organizerStripePlatform,
        Account $stripeAccount,
    ): void {
        $organizer = $this->organizerRepository->findById($organizerStripePlatform->getOrganizerId());
        if ($organizer === null) {
            return;
        }

        $account = $this->accountRepository->findById($organizer->getAccountId());
        if ($account === null) {
            return;
        }

        $updates = [];
        if (! $account->getCountry()) {
            $updates['country'] = strtoupper($stripeAccount->country);
        }

        if (! $account->getIsManuallyVerified()) {
            $updates['is_manually_verified'] = true;
        }

        if (! empty($updates)) {
            $this->accountRepository->updateWhere(
                attributes: $updates,
                where: [
                    'id' => $account->getId(),
                ]
            );
        }
    }
}
