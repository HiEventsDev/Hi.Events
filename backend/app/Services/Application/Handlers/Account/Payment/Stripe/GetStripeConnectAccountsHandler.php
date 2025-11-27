<?php

namespace HiEvents\Services\Application\Handlers\Account\Payment\Stripe;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountStripePlatformDomainObject;
use HiEvents\DomainObjects\Enums\StripePlatform;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Payment\Stripe\DTO\GetStripeConnectAccountsResponseDTO;
use HiEvents\Services\Application\Handlers\Account\Payment\Stripe\DTO\StripeConnectAccountDTO;
use HiEvents\Services\Domain\Payment\Stripe\StripeAccountSyncService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

class GetStripeConnectAccountsHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly StripeClientFactory        $stripeClientFactory,
        private readonly StripeAccountSyncService   $stripeAccountSyncService,
        private readonly LoggerInterface            $logger,
    )
    {
    }

    public function handle(int $accountId): GetStripeConnectAccountsResponseDTO
    {
        $account = $this->accountRepository
            ->loadRelation(AccountStripePlatformDomainObject::class)
            ->findById($accountId);

        $stripeConnectAccounts = $this->getStripeConnectAccounts($account);
        $primaryStripeAccountId = $account->getActiveStripeAccountId();
        $hasCompletedSetup = $account->isStripeSetupComplete();

        return new GetStripeConnectAccountsResponseDTO(
            account: $account,
            stripeConnectAccounts: $stripeConnectAccounts,
            primaryStripeAccountId: $primaryStripeAccountId,
            hasCompletedSetup: $hasCompletedSetup,
        );
    }

    private function getStripeConnectAccounts(AccountDomainObject $account): Collection
    {
        $stripeAccounts = collect();
        $stripePlatforms = $account->getAccountStripePlatforms();

        if (!$stripePlatforms || $stripePlatforms->isEmpty()) {
            return $stripeAccounts;
        }

        foreach ($stripePlatforms as $stripePlatform) {
            $stripeAccount = $this->getStripeAccount($stripePlatform);
            if ($stripeAccount) {
                $stripeAccounts->push($stripeAccount);
            }
        }

        return $stripeAccounts;
    }

    private function getStripeAccount(AccountStripePlatformDomainObject $stripePlatform): ?StripeConnectAccountDTO
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

            // Check if Stripe says setup is complete but our DB doesn't reflect it
            if ($isSetupComplete && $stripePlatform->getStripeSetupCompletedAt() === null) {
                $this->stripeAccountSyncService->markAccountAsComplete($stripePlatform, $stripeAccount);
            }

            // Generate connect URL if setup is not complete
            if (!$isSetupComplete) {
                $connectUrl = $this->stripeAccountSyncService->createStripeAccountSetupUrl($stripeAccount, $stripeClient);
            }

            return new StripeConnectAccountDTO(
                stripeAccountId: $stripeAccount->id,
                connectUrl: $connectUrl,
                isSetupComplete: $isSetupComplete,
                platform: $platform,
                accountType: $stripeAccount->type,
                isPrimary: $stripePlatform->getStripeSetupCompletedAt() !== null,
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
}
