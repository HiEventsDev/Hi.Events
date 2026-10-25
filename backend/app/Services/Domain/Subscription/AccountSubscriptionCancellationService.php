<?php

namespace HiEvents\Services\Domain\Subscription;

use HiEvents\DomainObjects\AccountSubscriptionDomainObject;
use HiEvents\DomainObjects\Generated\AccountSubscriptionDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AccountSubscriptionStatus;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Stripe\Subscription;

class AccountSubscriptionCancellationService
{
    private const SUBSCRIPTION_LIST_LIMIT = 100;

    public function __construct(
        private readonly AccountSubscriptionRepositoryInterface $accountSubscriptionRepository,
        private readonly StripeClientFactory $stripeClientFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws ApiErrorException
     * @throws StripeClientConfigurationException
     */
    public function cancelAtPeriodEnd(int $accountId): void
    {
        $accountSubscription = $this->findAccountSubscription($accountId);
        if ($accountSubscription === null) {
            return;
        }

        $stripeClient = $this->stripeClientFactory->createForBilling();
        $renewing = array_filter(
            $this->openSubscriptions($stripeClient, $accountSubscription),
            fn (Subscription $subscription) => ! $subscription->cancel_at_period_end,
        );

        foreach ($renewing as $subscription) {
            $stripeClient->subscriptions->update($subscription->id, ['cancel_at_period_end' => true]);
        }

        if ($renewing !== []) {
            $this->accountSubscriptionRepository->updateFromArray($accountSubscription->getId(), [
                AccountSubscriptionDomainObjectAbstract::CANCEL_AT_PERIOD_END => true,
            ]);

            $this->logger->info('Account subscriptions set to cancel at period end', [
                'account_id' => $accountId,
                'stripe_subscription_ids' => array_map(fn (Subscription $subscription) => $subscription->id, array_values($renewing)),
            ]);
        }
    }

    public function tryCancelAtPeriodEnd(int $accountId): void
    {
        try {
            $this->cancelAtPeriodEnd($accountId);
        } catch (ApiErrorException|StripeClientConfigurationException $exception) {
            $this->logger->warning('Failed to stop account subscription renewal', [
                'account_id' => $accountId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @throws ApiErrorException
     * @throws StripeClientConfigurationException
     */
    public function cancelImmediately(int $accountId): void
    {
        $accountSubscription = $this->findAccountSubscription($accountId);
        if ($accountSubscription === null) {
            return;
        }

        $stripeClient = $this->stripeClientFactory->createForBilling();
        $open = $this->openSubscriptions($stripeClient, $accountSubscription);

        foreach ($open as $subscription) {
            $stripeClient->subscriptions->cancel($subscription->id);
        }

        if ($accountSubscription->hasOpenSubscription() || $open !== []) {
            $this->accountSubscriptionRepository->updateFromArray($accountSubscription->getId(), [
                AccountSubscriptionDomainObjectAbstract::STATUS => AccountSubscriptionStatus::CANCELED->name,
            ]);
        }

        if ($open !== []) {
            $this->logger->info('Account subscriptions cancelled', [
                'account_id' => $accountId,
                'stripe_subscription_ids' => array_map(fn (Subscription $subscription) => $subscription->id, $open),
            ]);
        }
    }

    private function findAccountSubscription(int $accountId): ?AccountSubscriptionDomainObject
    {
        /** @var AccountSubscriptionDomainObject|null */
        return $this->accountSubscriptionRepository->findFirstWhere([
            AccountSubscriptionDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]);
    }

    /**
     * @return Subscription[]
     *
     * @throws ApiErrorException
     */
    private function openSubscriptions(StripeClient $stripeClient, AccountSubscriptionDomainObject $accountSubscription): array
    {
        try {
            $subscriptions = $stripeClient->subscriptions->all([
                'customer' => $accountSubscription->getStripeCustomerId(),
                'status' => 'all',
                'limit' => self::SUBSCRIPTION_LIST_LIMIT,
            ])->data;
        } catch (InvalidRequestException $exception) {
            if ($exception->getStripeCode() !== 'resource_missing') {
                throw $exception;
            }

            $this->logger->info('Stripe customer for account subscription no longer exists', [
                'account_id' => $accountSubscription->getAccountId(),
                'stripe_customer_id' => $accountSubscription->getStripeCustomerId(),
            ]);

            return [];
        }

        return array_values(array_filter(
            $subscriptions,
            fn (Subscription $subscription) => AccountSubscriptionStatus::fromStripeStatus($subscription->status)?->isOpen() ?? false,
        ));
    }
}
