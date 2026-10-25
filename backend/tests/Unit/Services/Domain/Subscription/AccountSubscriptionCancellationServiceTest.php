<?php

namespace Tests\Unit\Services\Domain\Subscription;

use HiEvents\DomainObjects\AccountSubscriptionDomainObject;
use HiEvents\DomainObjects\Status\AccountSubscriptionStatus;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionCancellationService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\NullLogger;
use Stripe\Collection;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;
use Stripe\Subscription;
use Tests\TestCase;

class AccountSubscriptionCancellationServiceTest extends TestCase
{
    private AccountSubscriptionRepositoryInterface|MockInterface $repository;

    private SubscriptionService|MockInterface $stripeSubscriptions;

    private AccountSubscriptionCancellationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(AccountSubscriptionRepositoryInterface::class);
        $this->stripeSubscriptions = Mockery::mock(SubscriptionService::class);

        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('subscriptions')->andReturn($this->stripeSubscriptions);

        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForBilling')->andReturn($stripeClient);

        $this->service = new AccountSubscriptionCancellationService($this->repository, $stripeClientFactory, new NullLogger);
    }

    public function test_cancel_at_period_end_stops_every_renewing_subscription(): void
    {
        $this->givenAccountSubscription(AccountSubscriptionStatus::ACTIVE);
        $this->givenStripeSubscriptions([
            $this->subscription('sub_1', 'active'),
            $this->subscription('sub_2', 'past_due'),
            $this->subscription('sub_ending', 'active', cancelAtPeriodEnd: true),
            $this->subscription('sub_old', 'canceled'),
        ]);

        $this->stripeSubscriptions->shouldReceive('update')->with('sub_1', ['cancel_at_period_end' => true])->once();
        $this->stripeSubscriptions->shouldReceive('update')->with('sub_2', ['cancel_at_period_end' => true])->once();
        $this->repository->shouldReceive('updateFromArray')->with(10, ['cancel_at_period_end' => true])->once();

        $this->service->cancelAtPeriodEnd(1);
    }

    public function test_trying_to_stop_renewal_swallows_stripe_errors(): void
    {
        $this->givenAccountSubscription(AccountSubscriptionStatus::ACTIVE);
        $this->stripeSubscriptions->shouldReceive('all')->andThrow(new ApiConnectionException('Stripe is unreachable'));

        $this->repository->shouldNotReceive('updateFromArray');

        $this->service->tryCancelAtPeriodEnd(1);
    }

    public function test_cancel_immediately_cancels_every_open_subscription(): void
    {
        $this->givenAccountSubscription(AccountSubscriptionStatus::ACTIVE);
        $this->givenStripeSubscriptions([
            $this->subscription('sub_1', 'active'),
            $this->subscription('sub_2', 'incomplete'),
            $this->subscription('sub_old', 'canceled'),
        ]);

        $this->stripeSubscriptions->shouldReceive('cancel')->with('sub_1')->once();
        $this->stripeSubscriptions->shouldReceive('cancel')->with('sub_2')->once();
        $this->repository->shouldReceive('updateFromArray')
            ->with(10, ['status' => AccountSubscriptionStatus::CANCELED->name])
            ->once();

        $this->service->cancelImmediately(1);
    }

    public function test_a_customer_deleted_in_stripe_does_not_block_cancellation(): void
    {
        $this->givenAccountSubscription(AccountSubscriptionStatus::ACTIVE);
        $this->stripeSubscriptions->shouldReceive('all')
            ->andThrow(InvalidRequestException::factory('No such customer', 404, null, null, null, 'resource_missing'));

        $this->stripeSubscriptions->shouldNotReceive('cancel');
        $this->repository->shouldReceive('updateFromArray')
            ->with(10, ['status' => AccountSubscriptionStatus::CANCELED->name])
            ->once();

        $this->service->cancelImmediately(1);
    }

    public function test_other_stripe_errors_still_fail_the_cancellation(): void
    {
        $this->givenAccountSubscription(AccountSubscriptionStatus::ACTIVE);
        $this->stripeSubscriptions->shouldReceive('all')
            ->andThrow(InvalidRequestException::factory('Invalid API key', 400));

        $this->repository->shouldNotReceive('updateFromArray');

        $this->expectException(InvalidRequestException::class);

        $this->service->cancelImmediately(1);
    }

    public function test_nothing_happens_for_accounts_that_never_subscribed(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->andReturnNull();

        $this->stripeSubscriptions->shouldNotReceive('all');

        $this->service->cancelImmediately(1);
        $this->service->cancelAtPeriodEnd(1);
    }

    public function test_ended_subscriptions_are_left_alone(): void
    {
        $this->givenAccountSubscription(AccountSubscriptionStatus::CANCELED);
        $this->givenStripeSubscriptions([$this->subscription('sub_old', 'canceled')]);

        $this->stripeSubscriptions->shouldNotReceive('cancel');
        $this->stripeSubscriptions->shouldNotReceive('update');
        $this->repository->shouldNotReceive('updateFromArray');

        $this->service->cancelImmediately(1);
        $this->service->cancelAtPeriodEnd(1);
    }

    private function givenAccountSubscription(AccountSubscriptionStatus $status): void
    {
        $this->repository->shouldReceive('findFirstWhere')
            ->with(['account_id' => 1])
            ->andReturn((new AccountSubscriptionDomainObject)
                ->setId(10)
                ->setAccountId(1)
                ->setStripeCustomerId('cus_1')
                ->setStripeSubscriptionId('sub_1')
                ->setStatus($status->name)
                ->setCancelAtPeriodEnd(false));
    }

    private function givenStripeSubscriptions(array $subscriptions): void
    {
        $this->stripeSubscriptions->shouldReceive('all')
            ->with(['customer' => 'cus_1', 'status' => 'all', 'limit' => 100])
            ->andReturn(Collection::constructFrom(['object' => 'list', 'data' => $subscriptions]));
    }

    private function subscription(string $id, string $status, bool $cancelAtPeriodEnd = false): Subscription
    {
        return Subscription::constructFrom([
            'id' => $id,
            'status' => $status,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
        ]);
    }
}
