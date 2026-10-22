<?php

namespace Tests\Unit\Services\Domain\Subscription;

use HiEvents\DomainObjects\AccountSubscriptionDomainObject;
use HiEvents\DomainObjects\Status\AccountSubscriptionStatus;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionAccessService;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountSubscriptionAccessServiceTest extends TestCase
{
    private AccountSubscriptionRepositoryInterface|MockInterface $repository;

    private Repository $config;

    private AccountSubscriptionAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(AccountSubscriptionRepositoryInterface::class);
        $this->config = new Repository(['app' => ['is_hi_events' => true]]);
        $this->service = new AccountSubscriptionAccessService($this->repository, $this->config);
    }

    public function test_subscriptions_are_never_active_outside_hi_events(): void
    {
        $this->config->set('app.is_hi_events', false);
        $this->repository->shouldNotReceive('findFirstWhere');

        $this->assertFalse($this->service->hasActiveSubscription(1));
    }

    public function test_asserting_availability_outside_hi_events_throws(): void
    {
        $this->config->set('app.is_hi_events', false);

        $this->expectException(FeatureNotEnabledException::class);

        $this->service->assertSubscriptionsAvailable();
    }

    public function test_an_account_without_a_subscription_has_no_access(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->with(['account_id' => 1])->andReturnNull();

        $this->assertFalse($this->service->hasActiveSubscription(1));
    }

    #[DataProvider('statuses')]
    public function test_access_follows_the_subscription_status(AccountSubscriptionStatus $status, bool $expected): void
    {
        $this->repository->shouldReceive('findFirstWhere')
            ->with(['account_id' => 1])
            ->andReturn((new AccountSubscriptionDomainObject)->setStatus($status->name));

        $this->assertSame($expected, $this->service->hasActiveSubscription(1));
    }

    public static function statuses(): array
    {
        return [
            'active' => [AccountSubscriptionStatus::ACTIVE, true],
            'trialing' => [AccountSubscriptionStatus::TRIALING, true],
            'past due during retries' => [AccountSubscriptionStatus::PAST_DUE, true],
            'incomplete first payment' => [AccountSubscriptionStatus::INCOMPLETE, false],
            'canceled' => [AccountSubscriptionStatus::CANCELED, false],
            'unpaid' => [AccountSubscriptionStatus::UNPAID, false],
            'paused' => [AccountSubscriptionStatus::PAUSED, false],
        ];
    }
}
