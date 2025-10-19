<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use Psr\Log\LoggerInterface;
use Stripe\Account;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

readonly class AccountUpdateHandler
{
    public function __construct(
        private LoggerInterface            $logger,
        private AccountRepositoryInterface $accountRepository,
    )
    {
    }

    public function handleEvent(Account $stripeAccount): void
    {
        $account = $this->accountRepository->findFirstWhere([
            'stripe_account_id' => $stripeAccount->id,
        ]);

        if ($account === null) {
            throw new ResourceNotFoundException(
                sprintf('Account with stripe account id %s not found', $stripeAccount->id)
            );
        }

        $isAccountSetupCompleted = $stripeAccount->charges_enabled && $stripeAccount->payouts_enabled;

        if ($account->getStripeConnectSetupComplete() === $isAccountSetupCompleted) {
            return;
        }

        $this->logger->info(sprintf(
                'Stripe connect account status change. Updating account %s with stripe account setup completed %s',
                $stripeAccount->id,
                $isAccountSetupCompleted ? 'true' : 'false'
            )
        );

        $this->accountRepository->updateWhere(
            attributes: [
                'stripe_connect_setup_complete' => $isAccountSetupCompleted,
            ],
            where: [
                'stripe_account_id' => $stripeAccount->id,
            ]
        );
    }
}
