<?php

namespace HiEvents\Services\Application\Handlers\Account\Subscription;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountSubscriptionDomainObject;
use HiEvents\DomainObjects\Generated\AccountSubscriptionDomainObjectAbstract;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Helper\Url;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Subscription\DTO\CreateSubscriptionCheckoutSessionDTO;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionAccessService;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionSyncService;
use HiEvents\Services\Domain\Subscription\DTO\SubscriptionPriceDTO;
use HiEvents\Services\Domain\Subscription\SubscriptionPriceService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class CreateSubscriptionCheckoutSessionHandler
{
    public function __construct(
        private readonly AccountSubscriptionAccessService $accountSubscriptionAccessService,
        private readonly AccountSubscriptionSyncService $accountSubscriptionSyncService,
        private readonly AccountSubscriptionRepositoryInterface $accountSubscriptionRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly AccountDeletionService $accountDeletionService,
        private readonly StripeClientFactory $stripeClientFactory,
        private readonly SubscriptionPriceService $subscriptionPriceService,
    ) {}

    /**
     * @throws ApiErrorException
     * @throws FeatureNotEnabledException
     * @throws ResourceConflictException
     * @throws StripeClientConfigurationException
     */
    public function handle(CreateSubscriptionCheckoutSessionDTO $dto): string
    {
        $this->accountSubscriptionAccessService->assertSubscriptionsAvailable();

        if ($this->accountDeletionService->isAccountPendingDeletion($dto->accountId)) {
            throw new ResourceConflictException(__('This account is scheduled for deletion.'));
        }

        if ($this->refreshExistingSubscription($dto->accountId)?->hasOpenSubscription()) {
            throw new ResourceConflictException(__('This account already has a subscription. Use Manage billing to change it.'));
        }

        /** @var AccountDomainObject $account */
        $account = $this->accountRepository->findById($dto->accountId);
        $stripeClient = $this->stripeClientFactory->createForBilling();
        $customerId = $this->getOrCreateStripeCustomerId($account, $stripeClient);
        $price = $this->findPrice($dto, $this->checkoutCurrency($account, $customerId, $stripeClient));

        $session = $stripeClient->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => (string) $dto->accountId,
            'currency' => strtolower($price->currency),
            'line_items' => [[
                'price' => $price->priceId,
                'quantity' => 1,
            ]],
            'allow_promotion_codes' => true,
            'automatic_tax' => ['enabled' => true],
            'tax_id_collection' => ['enabled' => true],
            'customer_update' => ['address' => 'auto', 'name' => 'auto'],
            'success_url' => Url::getFrontEndUrlFromConfig(Url::ACCOUNT_BILLING, ['checkout' => 'success']),
            'cancel_url' => Url::getFrontEndUrlFromConfig(Url::ACCOUNT_BILLING),
        ]);

        return $session->url;
    }

    /**
     * @throws ApiErrorException
     * @throws StripeClientConfigurationException
     */
    private function refreshExistingSubscription(int $accountId): ?AccountSubscriptionDomainObject
    {
        try {
            return $this->accountSubscriptionSyncService->refreshForAccount($accountId);
        } catch (InvalidRequestException $exception) {
            if ($exception->getStripeCode() !== 'resource_missing') {
                throw $exception;
            }

            $this->accountSubscriptionRepository->deleteWhere([
                AccountSubscriptionDomainObjectAbstract::ACCOUNT_ID => $accountId,
            ]);

            return null;
        }
    }

    /**
     * @throws StripeClientConfigurationException
     */
    private function findPrice(CreateSubscriptionCheckoutSessionDTO $dto, string $currency): SubscriptionPriceDTO
    {
        $price = collect($this->subscriptionPriceService->getPrices($currency))
            ->first(fn (SubscriptionPriceDTO $price) => $price->interval === $dto->interval);

        if ($price === null) {
            throw new StripeClientConfigurationException(
                'No active Stripe price has the lookup key '.$dto->interval->priceLookupKey()
            );
        }

        return $price;
    }

    /**
     * @throws ApiErrorException
     */
    private function checkoutCurrency(AccountDomainObject $account, string $customerId, StripeClient $stripeClient): string
    {
        $customerCurrency = $stripeClient->customers->retrieve($customerId)->currency;

        return strtoupper($customerCurrency ?: $account->getCurrencyCode());
    }

    /**
     * @throws ApiErrorException
     */
    private function getOrCreateStripeCustomerId(AccountDomainObject $account, StripeClient $stripeClient): string
    {
        $existing = $this->findAccountSubscription($account->getId());
        if ($existing !== null) {
            return $existing->getStripeCustomerId();
        }

        $customer = $stripeClient->customers->create([
            'email' => $account->getEmail(),
            'name' => $account->getName(),
            'metadata' => ['account_id' => (string) $account->getId()],
        ], ['idempotency_key' => 'account-subscription-customer-'.$account->getId()]);

        try {
            $this->accountSubscriptionRepository->create([
                AccountSubscriptionDomainObjectAbstract::ACCOUNT_ID => $account->getId(),
                AccountSubscriptionDomainObjectAbstract::STRIPE_CUSTOMER_ID => $customer->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->findAccountSubscription($account->getId())?->getStripeCustomerId() ?? $customer->id;
        }

        return $customer->id;
    }

    private function findAccountSubscription(int $accountId): ?AccountSubscriptionDomainObject
    {
        /** @var AccountSubscriptionDomainObject|null */
        return $this->accountSubscriptionRepository->findFirstWhere([
            AccountSubscriptionDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]);
    }
}
