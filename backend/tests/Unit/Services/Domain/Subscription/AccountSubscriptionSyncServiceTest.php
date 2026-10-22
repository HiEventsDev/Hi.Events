<?php

namespace Tests\Unit\Services\Domain\Subscription;

use HiEvents\DomainObjects\AccountSubscriptionDomainObject;
use HiEvents\DomainObjects\Status\AccountSubscriptionStatus;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionSyncService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\NullLogger;
use Stripe\Collection;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;
use Stripe\Subscription;
use Tests\TestCase;

class AccountSubscriptionSyncServiceTest extends TestCase
{
    private AccountSubscriptionRepositoryInterface|MockInterface $repository;

    private SubscriptionService|MockInterface $stripeSubscriptions;

    private AccountSubscriptionSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(AccountSubscriptionRepositoryInterface::class);
        $this->stripeSubscriptions = Mockery::mock(SubscriptionService::class);

        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('subscriptions')->andReturn($this->stripeSubscriptions);

        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForBilling')->andReturn($stripeClient);

        $this->service = new AccountSubscriptionSyncService($this->repository, $stripeClientFactory, new NullLogger);
    }

    public function test_webhooks_for_unknown_customers_are_ignored(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->with(['stripe_customer_id' => 'cus_unknown'])->andReturnNull();

        $this->stripeSubscriptions->shouldNotReceive('all');
        $this->repository->shouldNotReceive('updateFromArray');

        $this->service->syncCustomer('cus_unknown');
    }

    public function test_webhooks_store_the_customers_current_state_from_stripe(): void
    {
        $this->repository->shouldReceive('findFirstWhere')
            ->with(['stripe_customer_id' => 'cus_1'])
            ->andReturn($this->accountSubscription(subscriptionId: null, status: null));
        $this->givenCustomerSubscriptions([$this->stripeSubscription('sub_1', 'active', cancelAtPeriodEnd: true)]);

        $this->repository->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => $id === 10
                && $attributes['stripe_subscription_id'] === 'sub_1'
                && $attributes['status'] === AccountSubscriptionStatus::ACTIVE->name
                && $attributes['current_period_end']->getTimestamp() === 1_800_000_000
                && $attributes['cancel_at_period_end'] === true)
            ->andReturn($this->accountSubscription(subscriptionId: 'sub_1', status: AccountSubscriptionStatus::ACTIVE));

        $this->service->syncCustomer('cus_1');
    }

    public function test_refresh_does_nothing_for_accounts_that_never_started_checkout(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->with(['account_id' => 1])->andReturnNull();

        $this->stripeSubscriptions->shouldNotReceive('all');

        $this->assertNull($this->service->refreshForAccount(1));
    }

    public function test_refresh_keeps_the_row_when_checkout_was_abandoned(): void
    {
        $accountSubscription = $this->accountSubscription(subscriptionId: null, status: null);
        $this->repository->shouldReceive('findFirstWhere')->with(['account_id' => 1])->andReturn($accountSubscription);
        $this->givenCustomerSubscriptions([]);

        $this->repository->shouldNotReceive('updateFromArray');

        $this->assertSame($accountSubscription, $this->service->refreshForAccount(1));
    }

    public function test_a_subscription_that_grants_access_wins_over_newer_ended_ones(): void
    {
        $this->repository->shouldReceive('findFirstWhere')
            ->andReturn($this->accountSubscription(subscriptionId: 'sub_live', status: AccountSubscriptionStatus::ACTIVE));
        $this->givenCustomerSubscriptions([
            $this->stripeSubscription('sub_abandoned', 'incomplete_expired'),
            $this->stripeSubscription('sub_live', 'active'),
            $this->stripeSubscription('sub_old', 'canceled'),
        ]);

        $this->repository->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => $attributes['stripe_subscription_id'] === 'sub_live')
            ->andReturn($this->accountSubscription(subscriptionId: 'sub_live', status: AccountSubscriptionStatus::ACTIVE));

        $this->service->refreshForAccount(1);
    }

    public function test_an_older_unpaid_subscription_is_tracked_over_a_newer_ended_one(): void
    {
        $this->repository->shouldReceive('findFirstWhere')
            ->andReturn($this->accountSubscription(subscriptionId: null, status: null));
        $this->givenCustomerSubscriptions([
            $this->stripeSubscription('sub_new', 'canceled'),
            $this->stripeSubscription('sub_unpaid', 'unpaid'),
        ]);

        $this->repository->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => $attributes['stripe_subscription_id'] === 'sub_unpaid'
                && $attributes['status'] === AccountSubscriptionStatus::UNPAID->name)
            ->andReturn($this->accountSubscription(subscriptionId: 'sub_unpaid', status: AccountSubscriptionStatus::UNPAID));

        $this->assertTrue($this->service->refreshForAccount(1)->hasOpenSubscription());
    }

    public function test_a_cancellation_revokes_access(): void
    {
        $this->repository->shouldReceive('findFirstWhere')
            ->andReturn($this->accountSubscription(subscriptionId: 'sub_1', status: AccountSubscriptionStatus::ACTIVE));
        $this->givenCustomerSubscriptions([$this->stripeSubscription('sub_1', 'canceled')]);

        $this->repository->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => $attributes['status'] === AccountSubscriptionStatus::CANCELED->name)
            ->andReturn($this->accountSubscription(subscriptionId: 'sub_1', status: AccountSubscriptionStatus::CANCELED));

        $this->assertFalse($this->service->refreshForAccount(1)->grantsAccess());
    }

    private function givenCustomerSubscriptions(array $subscriptions): void
    {
        $this->stripeSubscriptions->shouldReceive('all')
            ->with(['customer' => 'cus_1', 'status' => 'all', 'limit' => 10])
            ->andReturn(Collection::constructFrom(['object' => 'list', 'data' => $subscriptions]));
    }

    private function accountSubscription(?string $subscriptionId, ?AccountSubscriptionStatus $status): AccountSubscriptionDomainObject
    {
        return (new AccountSubscriptionDomainObject)
            ->setId(10)
            ->setAccountId(1)
            ->setStripeCustomerId('cus_1')
            ->setStripeSubscriptionId($subscriptionId)
            ->setStatus($status?->name)
            ->setCancelAtPeriodEnd(false);
    }

    private function stripeSubscription(string $id, string $status, bool $cancelAtPeriodEnd = false): Subscription
    {
        return Subscription::constructFrom([
            'id' => $id,
            'object' => 'subscription',
            'customer' => 'cus_1',
            'status' => $status,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'items' => [
                'object' => 'list',
                'data' => [[
                    'id' => 'si_1',
                    'object' => 'subscription_item',
                    'current_period_end' => 1_800_000_000,
                ]],
            ],
        ]);
    }
}
