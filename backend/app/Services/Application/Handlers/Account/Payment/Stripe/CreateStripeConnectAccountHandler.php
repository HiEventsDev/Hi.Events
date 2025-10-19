<?php

namespace HiEvents\Services\Application\Handlers\Account\Payment\Stripe;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\StripeConnectAccountType;
use HiEvents\DomainObjects\Generated\AccountDomainObjectAbstract;
use HiEvents\Exceptions\CreateStripeConnectAccountFailedException;
use HiEvents\Exceptions\CreateStripeConnectAccountLinksFailedException;
use HiEvents\Exceptions\SaasModeEnabledException;
use HiEvents\Helper\Url;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Payment\Stripe\DTO\CreateStripeConnectAccountDTO;
use HiEvents\Services\Application\Handlers\Account\Payment\Stripe\DTO\CreateStripeConnectAccountResponse;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Stripe\Account;
use Stripe\StripeClient;
use Throwable;

readonly class CreateStripeConnectAccountHandler
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository,
        private DatabaseManager            $databaseManager,
        private StripeClient               $stripe,
        private LoggerInterface            $logger,
        private Repository                 $config,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(CreateStripeConnectAccountDTO $command): CreateStripeConnectAccountResponse
    {
        if (!$this->config->get('app.saas_mode_enabled')) {
            throw new SaasModeEnabledException(
                __('Stripe Connect Account creation is only available in Saas Mode.'),
            );
        }

        return $this->databaseManager->transaction(fn() => $this->createOrGetStripeConnectAccount($command));
    }

    /**
     * @throws CreateStripeConnectAccountFailedException|CreateStripeConnectAccountLinksFailedException
     */
    private function createOrGetStripeConnectAccount(CreateStripeConnectAccountDTO $command): CreateStripeConnectAccountResponse
    {
        $account = $this->accountRepository->findById($command->accountId);

        $stripeConnectAccount = $this->getOrCreateStripeConnectAccount(
            account: $account,
        );

        $response = new CreateStripeConnectAccountResponse(
            stripeConnectAccountType: $stripeConnectAccount->type,
            stripeAccountId: $stripeConnectAccount->id,
            account: $account,
            isConnectSetupComplete: $this->isStripeAccountComplete($stripeConnectAccount),
        );

        if ($response->isConnectSetupComplete) {
            // If setup is complete, but this isn't reflected in the account, update it.
            if ($account->getStripeConnectSetupComplete() === false) {
                $this->updateAccountStripeSetupCompletionStatus($account, $stripeConnectAccount);
            }

            return $response;
        }

        $response->connectUrl = $this->getStripeAccountSetupUrl($stripeConnectAccount, $account);

        return $response;
    }

    /**
     * @throws CreateStripeConnectAccountFailedException
     */
    private function getOrCreateStripeConnectAccount(AccountDomainObject $account): Account
    {
        try {
            if ($account->getStripeAccountId() !== null) {
                return $this->stripe->accounts->retrieve($account->getStripeAccountId());
            }

            $stripeAccount = $this->stripe->accounts->create([
                'type' => $this->config->get('app.stripe_connect_account_type')
                    ?? StripeConnectAccountType::EXPRESS->value,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to create or fetch Stripe Connect Account: ' . $e->getMessage(), [
                'accountId' => $account->getId(),
                'stripeAccountId' => $account->getStripeAccountId() ?? 'null',
                'accountExists' => $account->getStripeAccountId() !== null ? 'true' : 'false',
                'exception' => $e,
            ]);

            throw new CreateStripeConnectAccountFailedException(
                message: __('There are issues with creating or fetching the Stripe Connect Account. Please try again.'),
                previous: $e,
            );
        }

        $this->accountRepository->updateWhere(
            attributes: [
                AccountDomainObjectAbstract::STRIPE_ACCOUNT_ID => $stripeAccount->id,
                AccountDomainObjectAbstract::STRIPE_CONNECT_ACCOUNT_TYPE => $stripeAccount->type,
            ],
            where: [
                'id' => $account->getId(),
            ]
        );

        return $stripeAccount;
    }

    /**
     * @param Account $stripAccount
     * @return bool
     */
    private function isStripeAccountComplete(Account $stripAccount): bool
    {
        return $stripAccount->charges_enabled
            && $stripAccount->payouts_enabled;
    }

    /**
     * @throws CreateStripeConnectAccountLinksFailedException
     */
    private function getStripeAccountSetupUrl(Account $stripAccount, AccountDomainObject $account): string
    {
        try {
            $accountLink = $this->stripe->accountLinks->create([
                'account' => $stripAccount->id,
                'refresh_url' => Url::getFrontEndUrlFromConfig(Url::STRIPE_CONNECT_REFRESH_URL, [
                    'is_refresh' => true,
                ]),
                'return_url' => Url::getFrontEndUrlFromConfig(Url::STRIPE_CONNECT_RETURN_URL, [
                    'is_return' => true,
                ]),
                'type' => 'account_onboarding',
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Failed to create Stripe Connect Account Link: ' . $e->getMessage(), [
                'accountId' => $account->getId(),
                'stripeAccountId' => $stripAccount->id,
                'exception' => $e,
            ]);

            throw new CreateStripeConnectAccountLinksFailedException(
                message: __('There are issues with creating the Stripe Connect Account Link. Please try again.'),
                previous: $e,
            );
        }

        return $accountLink->url;
    }

    private function updateAccountStripeSetupCompletionStatus(
        AccountDomainObject $account,
        Account             $stripeConnectAccount,
    ): void
    {
        $this->accountRepository->updateWhere(
            attributes: [
                AccountDomainObjectAbstract::STRIPE_CONNECT_SETUP_COMPLETE => true,
            ],
            where: [
                'id' => $account->getId(),
            ]
        );

        $this->logger->info(sprintf(
            'Stripe Connect account setup completed for account %s with Stripe account ID %s',
            $account->getId(),
            $stripeConnectAccount->id
        ));
    }
}
