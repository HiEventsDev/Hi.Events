<?php

namespace HiEvents\Services\Application\Handlers\Account\Subscription;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountSubscriptionDomainObject;
use HiEvents\DomainObjects\Generated\AccountSubscriptionDomainObjectAbstract;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Subscription\DTO\AccountSubscriptionSummaryDTO;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionAccessService;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionSyncService;
use HiEvents\Services\Domain\Subscription\SubscriptionPriceService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;

class GetAccountSubscriptionHandler
{
    public function __construct(
        private readonly AccountSubscriptionRepositoryInterface $accountSubscriptionRepository,
        private readonly AccountSubscriptionAccessService $accountSubscriptionAccessService,
        private readonly AccountSubscriptionSyncService $accountSubscriptionSyncService,
        private readonly SubscriptionPriceService $subscriptionPriceService,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(int $accountId, bool $refreshFromStripe): AccountSubscriptionSummaryDTO
    {
        if (! $this->accountSubscriptionAccessService->areSubscriptionsAvailable()) {
            return new AccountSubscriptionSummaryDTO(available: false, active: false);
        }

        $accountSubscription = $refreshFromStripe
            ? $this->refreshFromStripe($accountId)
            : $this->findAccountSubscription($accountId);

        /** @var AccountDomainObject $account */
        $account = $this->accountRepository->findById($accountId);
        $prices = $this->subscriptionPriceService->getPrices($account->getCurrencyCode());

        return new AccountSubscriptionSummaryDTO(
            available: $prices !== [] || $accountSubscription?->getStatus() !== null,
            active: $accountSubscription?->grantsAccess() ?? false,
            status: $accountSubscription?->getStatus(),
            currentPeriodEnd: $accountSubscription?->getCurrentPeriodEnd(),
            cancelAtPeriodEnd: $accountSubscription?->getCancelAtPeriodEnd() ?? false,
            prices: $prices,
        );
    }

    private function refreshFromStripe(int $accountId): ?AccountSubscriptionDomainObject
    {
        try {
            return $this->accountSubscriptionSyncService->refreshForAccount($accountId);
        } catch (ApiErrorException|StripeClientConfigurationException $exception) {
            $this->logger->warning('Failed to refresh the account subscription from Stripe', [
                'account_id' => $accountId,
                'error' => $exception->getMessage(),
            ]);

            return $this->findAccountSubscription($accountId);
        }
    }

    private function findAccountSubscription(int $accountId): ?AccountSubscriptionDomainObject
    {
        /** @var AccountSubscriptionDomainObject|null */
        return $this->accountSubscriptionRepository->findFirstWhere([
            AccountSubscriptionDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]);
    }
}
