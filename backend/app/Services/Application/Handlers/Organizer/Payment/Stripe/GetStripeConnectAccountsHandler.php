<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe;

use HiEvents\DomainObjects\Enums\StripePlatform;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\GetStripeConnectAccountsResponseDTO;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\ReusableStripeConnectionDTO;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\StripeConnectAccountDTO;
use HiEvents\Services\Domain\Payment\Stripe\StripeAccountSyncService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

class GetStripeConnectAccountsHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface               $organizerRepository,
        private readonly OrganizerStripePlatformRepositoryInterface $organizerStripePlatformRepository,
        private readonly StripeClientFactory                        $stripeClientFactory,
        private readonly StripeAccountSyncService                   $stripeAccountSyncService,
        private readonly LoggerInterface                            $logger,
    )
    {
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $accountId): GetStripeConnectAccountsResponseDTO
    {
        $organizer = $this->organizerRepository
            ->loadRelation(OrganizerStripePlatformDomainObject::class)
            ->findFirstWhere([
                'id' => $organizerId,
                'account_id' => $accountId,
            ]);

        if ($organizer === null) {
            throw new ResourceNotFoundException(__('Organizer not found.'));
        }

        $stripeConnectAccounts = $this->getStripeConnectAccounts($organizer);
        $primaryStripeAccountId = $organizer->getActiveStripeAccountId();
        $hasCompletedSetup = $organizer->isStripeSetupComplete();
        $reusable = $this->getReusableConnections($accountId, $organizerId, $primaryStripeAccountId);

        return new GetStripeConnectAccountsResponseDTO(
            organizer: $organizer,
            stripeConnectAccounts: $stripeConnectAccounts,
            reusableConnections: $reusable,
            primaryStripeAccountId: $primaryStripeAccountId,
            hasCompletedSetup: $hasCompletedSetup,
        );
    }

    private function getStripeConnectAccounts(OrganizerDomainObject $organizer): Collection
    {
        $stripeAccounts = collect();
        $stripePlatforms = $organizer->getOrganizerStripePlatforms();

        if (!$stripePlatforms || $stripePlatforms->isEmpty()) {
            return $stripeAccounts;
        }

        foreach ($stripePlatforms as $stripePlatform) {
            $stripeAccount = $this->buildStripeAccountDTO($stripePlatform);
            if ($stripeAccount) {
                $stripeAccounts->push($stripeAccount);
            }
        }

        return $stripeAccounts;
    }

    private function buildStripeAccountDTO(OrganizerStripePlatformDomainObject $stripePlatform): ?StripeConnectAccountDTO
    {
        if (!$stripePlatform->getStripeAccountId()) {
            return null;
        }

        try {
            $platform = $stripePlatform->getStripeConnectPlatform()
                ? StripePlatform::fromString($stripePlatform->getStripeConnectPlatform())
                : null;

            $stripeClient = $this->stripeClientFactory->createForPlatform($platform);
            $stripeAccount = $stripeClient->accounts->retrieve($stripePlatform->getStripeAccountId());

            $isSetupComplete = $this->stripeAccountSyncService->isStripeAccountComplete($stripeAccount);
            $connectUrl = null;

            if ($isSetupComplete && $stripePlatform->getStripeSetupCompletedAt() === null) {
                $this->stripeAccountSyncService->markAccountAsCompleteForOrganizer($stripePlatform, $stripeAccount);
            } else {
                $this->stripeAccountSyncService->syncStripeAccountDetailsForOrganizer($stripePlatform, $stripeAccount);
            }

            if (!$isSetupComplete) {
                $connectUrl = $this->stripeAccountSyncService->createStripeAccountSetupUrl($stripeAccount, $stripeClient, $stripePlatform->getOrganizerId());
            }

            $details = is_array($stripePlatform->getStripeAccountDetails())
                ? $stripePlatform->getStripeAccountDetails()
                : (is_string($stripePlatform->getStripeAccountDetails())
                    ? (json_decode($stripePlatform->getStripeAccountDetails(), true) ?? [])
                    : []);

            return new StripeConnectAccountDTO(
                stripeAccountId: $stripeAccount->id,
                connectUrl: $connectUrl,
                isSetupComplete: $isSetupComplete,
                platform: $platform,
                accountType: $stripeAccount->type,
                isPrimary: $isSetupComplete,
                country: $stripeAccount->country ?? ($details['country'] ?? null),
                businessType: $stripeAccount->business_type ?? ($details['business_type'] ?? null),
                chargesEnabled: (bool)($stripeAccount->charges_enabled ?? false),
                payoutsEnabled: (bool)($stripeAccount->payouts_enabled ?? false),
                capabilities: $this->normalizeCapabilities($stripeAccount),
                requirements: $this->normalizeRequirements($stripeAccount),
            );
        } catch (StripeClientConfigurationException $e) {
            $this->logger->warning('Failed to retrieve Stripe account due to configuration issue', [
                'stripe_account_id' => $stripePlatform->getStripeAccountId(),
                'platform' => $stripePlatform->getStripeConnectPlatform(),
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (Throwable $e) {
            $this->logger->error('Failed to retrieve Stripe account', [
                'stripe_account_id' => $stripePlatform->getStripeAccountId(),
                'platform' => $stripePlatform->getStripeConnectPlatform(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function normalizeCapabilities(\Stripe\Account $stripeAccount): array
    {
        $capabilities = $stripeAccount->capabilities;
        if (is_array($capabilities)) {
            return $capabilities;
        }
        if ($capabilities && method_exists($capabilities, 'toArray')) {
            return $capabilities->toArray();
        }
        return [];
    }

    private function normalizeRequirements(\Stripe\Account $stripeAccount): array
    {
        $requirements = $stripeAccount->requirements;
        return [
            'currently_due' => $requirements?->currently_due ?? [],
            'eventually_due' => $requirements?->eventually_due ?? [],
            'past_due' => $requirements?->past_due ?? [],
            'pending_verification' => $requirements?->pending_verification ?? [],
        ];
    }

    private function getReusableConnections(int $accountId, int $excludeOrganizerId, ?string $currentStripeAccountId): Collection
    {
        $rows = $this->organizerStripePlatformRepository->findReusableForAccount(
            $accountId,
            $excludeOrganizerId,
            $currentStripeAccountId,
        );

        $seen = [];
        $result = collect();

        foreach ($rows as $row) {
            $stripeAccountId = $row->stripe_account_id;
            if (isset($seen[$stripeAccountId])) {
                continue;
            }
            $seen[$stripeAccountId] = true;

            $details = $row->stripe_account_details;
            if (is_string($details)) {
                $details = json_decode($details, true) ?? [];
            }
            if (!is_array($details)) {
                $details = [];
            }

            $result->push(new ReusableStripeConnectionDTO(
                organizerId: (int)$row->organizer_id,
                organizerName: (string)$row->organizer_name,
                stripeAccountId: $stripeAccountId,
                platform: $row->stripe_connect_platform,
                country: $details['country'] ?? null,
                businessType: $details['business_type'] ?? null,
            ));
        }

        return $result;
    }
}
