<?php

namespace HiEvents\Services\Domain\Subscription;

use Carbon\Carbon;
use HiEvents\DomainObjects\AccountSubscriptionDomainObject;
use HiEvents\DomainObjects\Generated\AccountSubscriptionDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AccountSubscriptionStatus;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Subscription;

class AccountSubscriptionSyncService
{
    private const SUBSCRIPTION_LIST_LIMIT = 10;

    public function __construct(
        private readonly AccountSubscriptionRepositoryInterface $accountSubscriptionRepository,
        private readonly StripeClientFactory $stripeClientFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws ApiErrorException
     * @throws StripeClientConfigurationException
     */
    public function syncCustomer(string $stripeCustomerId): void
    {
        /** @var AccountSubscriptionDomainObject|null $accountSubscription */
        $accountSubscription = $this->accountSubscriptionRepository->findFirstWhere([
            AccountSubscriptionDomainObjectAbstract::STRIPE_CUSTOMER_ID => $stripeCustomerId,
        ]);

        if ($accountSubscription === null) {
            $this->logger->info('Ignoring Stripe subscription for a customer with no account subscription', [
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            return;
        }

        $this->refresh($accountSubscription);
    }

    /**
     * @throws ApiErrorException
     * @throws StripeClientConfigurationException
     */
    public function refreshForAccount(int $accountId): ?AccountSubscriptionDomainObject
    {
        /** @var AccountSubscriptionDomainObject|null $accountSubscription */
        $accountSubscription = $this->accountSubscriptionRepository->findFirstWhere([
            AccountSubscriptionDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]);

        return $accountSubscription === null ? null : $this->refresh($accountSubscription);
    }

    /**
     * @throws ApiErrorException
     * @throws StripeClientConfigurationException
     */
    private function refresh(AccountSubscriptionDomainObject $accountSubscription): AccountSubscriptionDomainObject
    {
        $subscriptions = $this->stripeClientFactory
            ->createForBilling()
            ->subscriptions
            ->all([
                'customer' => $accountSubscription->getStripeCustomerId(),
                'status' => 'all',
                'limit' => self::SUBSCRIPTION_LIST_LIMIT,
            ])
            ->data;

        $subscription = $this->mostRelevantSubscription($subscriptions);

        if ($subscription === null) {
            return $accountSubscription;
        }

        $status = AccountSubscriptionStatus::fromStripeStatus($subscription->status);
        $currentPeriodEnd = $subscription->items->data[0]->current_period_end ?? null;

        /** @var AccountSubscriptionDomainObject $updated */
        $updated = $this->accountSubscriptionRepository->updateFromArray($accountSubscription->getId(), [
            AccountSubscriptionDomainObjectAbstract::STRIPE_SUBSCRIPTION_ID => $subscription->id,
            AccountSubscriptionDomainObjectAbstract::STATUS => $status?->name,
            AccountSubscriptionDomainObjectAbstract::CURRENT_PERIOD_END => $currentPeriodEnd
                ? Carbon::createFromTimestamp($currentPeriodEnd)
                : null,
            AccountSubscriptionDomainObjectAbstract::CANCEL_AT_PERIOD_END => (bool) $subscription->cancel_at_period_end,
        ]);

        if ($updated->getStatus() !== $accountSubscription->getStatus()) {
            $this->logger->info('Account subscription status changed', [
                'account_id' => $accountSubscription->getAccountId(),
                'stripe_subscription_id' => $subscription->id,
                'from' => $accountSubscription->getStatus(),
                'to' => $updated->getStatus(),
            ]);
        }

        return $updated;
    }

    /**
     * @param  Subscription[]  $subscriptions
     */
    private function mostRelevantSubscription(array $subscriptions): ?Subscription
    {
        $statusOf = fn (Subscription $subscription) => AccountSubscriptionStatus::fromStripeStatus($subscription->status);

        return collect($subscriptions)->first(fn (Subscription $subscription) => $statusOf($subscription)?->grantsAccess())
            ?? collect($subscriptions)->first(fn (Subscription $subscription) => $statusOf($subscription)?->isOpen())
            ?? $subscriptions[0]
            ?? null;
    }
}
