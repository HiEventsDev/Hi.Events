<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountStripePlatformDomainObject;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountStripePlatformRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\StripeAccountSyncService;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Stripe\Account;
use Tests\TestCase;

class StripeAccountSyncServiceTest extends TestCase
{
    private StripeAccountSyncService $service;
    private LoggerInterface $logger;
    private AccountRepositoryInterface $accountRepository;
    private AccountStripePlatformRepositoryInterface $accountStripePlatformRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = m::mock(LoggerInterface::class);
        $this->accountRepository = m::mock(AccountRepositoryInterface::class);
        $this->accountStripePlatformRepository = m::mock(AccountStripePlatformRepositoryInterface::class);

        $this->service = new StripeAccountSyncService(
            $this->logger,
            $this->accountRepository,
            $this->accountStripePlatformRepository,
        );
    }

    public function testIsStripeAccountCompleteReturnsTrueWhenBothEnabled(): void
    {
        $stripeAccount = new \stdClass();
        $stripeAccount->charges_enabled = true;
        $stripeAccount->payouts_enabled = true;

        $result = $this->service->isStripeAccountComplete($stripeAccount);

        $this->assertTrue($result);
    }

    public function testIsStripeAccountCompleteReturnsFalseWhenChargesDisabled(): void
    {
        $stripeAccount = new \stdClass();
        $stripeAccount->charges_enabled = false;
        $stripeAccount->payouts_enabled = true;

        $result = $this->service->isStripeAccountComplete($stripeAccount);

        $this->assertFalse($result);
    }

    public function testIsStripeAccountCompleteReturnsFalseWhenPayoutsDisabled(): void
    {
        $stripeAccount = new \stdClass();
        $stripeAccount->charges_enabled = true;
        $stripeAccount->payouts_enabled = false;

        $result = $this->service->isStripeAccountComplete($stripeAccount);

        $this->assertFalse($result);
    }

    public function testIsStripeAccountCompleteReturnsFalseWhenBothDisabled(): void
    {
        $stripeAccount = new \stdClass();
        $stripeAccount->charges_enabled = false;
        $stripeAccount->payouts_enabled = false;

        $result = $this->service->isStripeAccountComplete($stripeAccount);

        $this->assertFalse($result);
    }

    public function testMarkAccountAsCompleteLogsCorrectMessage(): void
    {
        $accountStripePlatform = m::mock(AccountStripePlatformDomainObject::class);
        $stripeAccount = new \stdClass();

        $accountStripePlatform
            ->shouldReceive('getId')
            ->andReturn(123);

        $accountStripePlatform
            ->shouldReceive('getAccountId')
            ->andReturn(1);

        $stripeAccount->id = 'acct_test123';
        $stripeAccount->charges_enabled = true;
        $stripeAccount->payouts_enabled = true;
        $stripeAccount->country = 'IE';
        $stripeAccount->type = 'express';
        $stripeAccount->business_type = 'individual';
        $stripeAccount->capabilities = null;
        $stripeAccount->requirements = null;

        $this->logger
            ->shouldReceive('info')
            ->with('Marking Stripe Connect account as complete for account stripe platform 123 with Stripe account ID acct_test123');

        $this->accountStripePlatformRepository
            ->shouldReceive('updateWhere')
            ->once();

        $account = m::mock(AccountDomainObject::class);
        $this->accountRepository
            ->shouldReceive('findById')
            ->with(1)
            ->andReturn($account);

        $account
            ->shouldReceive('getIsManuallyVerified')
            ->andReturn(false);

        $this->accountRepository
            ->shouldReceive('updateWhere')
            ->with(
                ['is_manually_verified' => true],
                ['id' => 1]
            );

        $this->service->markAccountAsComplete($accountStripePlatform, $stripeAccount);
    }

    public function testMarkAccountAsCompleteSkipsVerificationIfAlreadyVerified(): void
    {
        $accountStripePlatform = m::mock(AccountStripePlatformDomainObject::class);
        $stripeAccount = m::mock(Account::class);

        $accountStripePlatform
            ->shouldReceive('getId')
            ->andReturn(123);

        $accountStripePlatform
            ->shouldReceive('getAccountId')
            ->andReturn(1);

        $stripeAccount->id = 'acct_already_verified123';
        $stripeAccount->charges_enabled = true;
        $stripeAccount->payouts_enabled = true;
        $stripeAccount->country = 'IE';
        $stripeAccount->type = 'express';
        $stripeAccount->business_type = 'individual';
        $stripeAccount->capabilities = null;
        $stripeAccount->requirements = null;

        $this->logger
            ->shouldReceive('info')
            ->with('Marking Stripe Connect account as complete for account stripe platform 123 with Stripe account ID acct_already_verified123');

        $this->accountStripePlatformRepository
            ->shouldReceive('updateWhere')
            ->once();

        // Account is already verified
        $account = m::mock(AccountDomainObject::class);
        $this->accountRepository
            ->shouldReceive('findById')
            ->with(1)
            ->andReturn($account);

        $account
            ->shouldReceive('getIsManuallyVerified')
            ->andReturn(true);

        // Should NOT update verification status
        $this->accountRepository
            ->shouldNotReceive('updateWhere');

        $this->service->markAccountAsComplete($accountStripePlatform, $stripeAccount);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}