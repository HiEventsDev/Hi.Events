<?php

namespace HiEvents\Services\Domain\Subscription;

use HiEvents\DomainObjects\Generated\AccountSubscriptionDomainObjectAbstract;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;
use Illuminate\Config\Repository;

class AccountSubscriptionAccessService
{
    public function __construct(
        private readonly AccountSubscriptionRepositoryInterface $accountSubscriptionRepository,
        private readonly Repository $config,
    ) {}

    public function areSubscriptionsAvailable(): bool
    {
        return (bool) $this->config->get('app.is_hi_events');
    }

    /**
     * @throws FeatureNotEnabledException
     */
    public function assertSubscriptionsAvailable(): void
    {
        if (! $this->areSubscriptionsAvailable()) {
            throw new FeatureNotEnabledException;
        }
    }

    public function hasActiveSubscription(int $accountId): bool
    {
        if (! $this->areSubscriptionsAvailable()) {
            return false;
        }

        $subscription = $this->accountSubscriptionRepository->findFirstWhere([
            AccountSubscriptionDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]);

        return $subscription?->grantsAccess() ?? false;
    }
}
