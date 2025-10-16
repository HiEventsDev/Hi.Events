<?php

namespace Tests\Unit\Services\Application\Handlers\Account\Payment\Stripe;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountStripePlatformDomainObject;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Payment\Stripe\GetStripeConnectAccountsHandler;
use HiEvents\Services\Domain\Payment\Stripe\StripeAccountSyncService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class GetStripeConnectAccountsHandlerTest extends TestCase
{
    private GetStripeConnectAccountsHandler $handler;
    private AccountRepositoryInterface $accountRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountRepository = m::mock(AccountRepositoryInterface::class);
        $stripeClientFactory = m::mock(StripeClientFactory::class);
        $stripeAccountSyncService = m::mock(StripeAccountSyncService::class);
        $logger = m::mock(LoggerInterface::class);

        $this->handler = new GetStripeConnectAccountsHandler(
            $this->accountRepository,
            $stripeClientFactory,
            $stripeAccountSyncService,
            $logger,
        );
    }

    public function testHandleReturnsEmptyCollectionWhenNoStripePlatforms(): void
    {
        $accountId = 1;
        $account = m::mock(AccountDomainObject::class);

        $this->accountRepository
            ->shouldReceive('loadRelation')
            ->with(AccountStripePlatformDomainObject::class)
            ->andReturnSelf();

        $this->accountRepository
            ->shouldReceive('findById')
            ->with($accountId)
            ->andReturn($account);

        $account
            ->shouldReceive('getAccountStripePlatforms')
            ->andReturn(null);

        $account
            ->shouldReceive('getActiveStripeAccountId')
            ->andReturn(null);

        $account
            ->shouldReceive('isStripeSetupComplete')
            ->andReturn(false);

        $result = $this->handler->handle($accountId);

        $this->assertSame($account, $result->account);
        $this->assertTrue($result->stripeConnectAccounts->isEmpty());
        $this->assertNull($result->primaryStripeAccountId);
        $this->assertFalse($result->hasCompletedSetup);
    }

    public function testHandleReturnsEmptyCollectionWhenStripePlatformsEmpty(): void
    {
        $accountId = 1;
        $account = m::mock(AccountDomainObject::class);
        $emptyCollection = collect([]);

        $this->accountRepository
            ->shouldReceive('loadRelation')
            ->with(AccountStripePlatformDomainObject::class)
            ->andReturnSelf();

        $this->accountRepository
            ->shouldReceive('findById')
            ->with($accountId)
            ->andReturn($account);

        $account
            ->shouldReceive('getAccountStripePlatforms')
            ->andReturn($emptyCollection);

        $account
            ->shouldReceive('getActiveStripeAccountId')
            ->andReturn(null);

        $account
            ->shouldReceive('isStripeSetupComplete')
            ->andReturn(false);

        $result = $this->handler->handle($accountId);

        $this->assertTrue($result->stripeConnectAccounts->isEmpty());
    }

    public function testHandleSkipsAccountWithoutStripeAccountId(): void
    {
        $accountId = 1;
        $account = m::mock(AccountDomainObject::class);
        $stripePlatform = m::mock(AccountStripePlatformDomainObject::class);
        $stripePlatforms = collect([$stripePlatform]);

        $this->accountRepository
            ->shouldReceive('loadRelation')
            ->with(AccountStripePlatformDomainObject::class)
            ->andReturnSelf();

        $this->accountRepository
            ->shouldReceive('findById')
            ->with($accountId)
            ->andReturn($account);

        $account
            ->shouldReceive('getAccountStripePlatforms')
            ->andReturn($stripePlatforms);

        $stripePlatform
            ->shouldReceive('getStripeAccountId')
            ->andReturn(null);

        $account
            ->shouldReceive('getActiveStripeAccountId')
            ->andReturn(null);

        $account
            ->shouldReceive('isStripeSetupComplete')
            ->andReturn(false);

        $result = $this->handler->handle($accountId);

        $this->assertTrue($result->stripeConnectAccounts->isEmpty());
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
