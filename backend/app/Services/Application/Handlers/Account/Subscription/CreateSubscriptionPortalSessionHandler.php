<?php

namespace HiEvents\Services\Application\Handlers\Account\Subscription;

use HiEvents\DomainObjects\AccountSubscriptionDomainObject;
use HiEvents\DomainObjects\Generated\AccountSubscriptionDomainObjectAbstract;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Helper\Url;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionAccessService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Stripe\Exception\ApiErrorException;

class CreateSubscriptionPortalSessionHandler
{
    public function __construct(
        private readonly AccountSubscriptionAccessService $accountSubscriptionAccessService,
        private readonly AccountSubscriptionRepositoryInterface $accountSubscriptionRepository,
        private readonly StripeClientFactory $stripeClientFactory,
    ) {}

    /**
     * @throws ApiErrorException
     * @throws FeatureNotEnabledException
     * @throws ResourceNotFoundException
     * @throws StripeClientConfigurationException
     */
    public function handle(int $accountId): string
    {
        $this->accountSubscriptionAccessService->assertSubscriptionsAvailable();

        /** @var AccountSubscriptionDomainObject|null $accountSubscription */
        $accountSubscription = $this->accountSubscriptionRepository->findFirstWhere([
            AccountSubscriptionDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]);

        if ($accountSubscription?->getStripeSubscriptionId() === null) {
            throw new ResourceNotFoundException(__('This account has no subscription to manage.'));
        }

        $session = $this->stripeClientFactory->createForBilling()->billingPortal->sessions->create([
            'customer' => $accountSubscription->getStripeCustomerId(),
            'return_url' => Url::getFrontEndUrlFromConfig(Url::ACCOUNT_BILLING),
        ]);

        return $session->url;
    }
}
