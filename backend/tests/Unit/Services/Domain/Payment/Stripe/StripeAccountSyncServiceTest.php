<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountStripePlatformDomainObject;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountStripePlatformRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\StripeAccountSyncService;
use Illuminate\Config\Repository;
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
    private AccountVatSettingRepositoryInterface $vatSettingRepository;
    private Repository $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = m::mock(LoggerInterface::class);
        $this->accountRepository = m::mock(AccountRepositoryInterface::class);
        $this->accountStripePlatformRepository = m::mock(AccountStripePlatformRepositoryInterface::class);
        $this->vatSettingRepository = m::mock(AccountVatSettingRepositoryInterface::class);
        $this->config = m::mock(Repository::class);

        $this->service = new StripeAccountSyncService(
            $this->logger,
            $this->accountRepository,
            $this->accountStripePlatformRepository,
            $this->vatSettingRepository,
            $this->config,
        );
    }

    public function testIsStripeAccountCompleteReturnsTrueWhenBothEnabled(): void
    {
        $stripeAccount = new Account();
        $stripeAccount->charges_enabled = true;
        $stripeAccount->payouts_enabled = true;

        $result = $this->service->isStripeAccountComplete($stripeAccount);

        $this->assertTrue($result);
    }

    public function testIsStripeAccountCompleteReturnsFalseWhenChargesDisabled(): void
    {
        $stripeAccount = new Account();
        $stripeAccount->charges_enabled = false;
        $stripeAccount->payouts_enabled = true;

        $result = $this->service->isStripeAccountComplete($stripeAccount);

        $this->assertFalse($result);
    }

    public function testIsStripeAccountCompleteReturnsFalseWhenPayoutsDisabled(): void
    {
        $stripeAccount = new Account();
        $stripeAccount->charges_enabled = true;
        $stripeAccount->payouts_enabled = false;

        $result = $this->service->isStripeAccountComplete($stripeAccount);

        $this->assertFalse($result);
    }

    public function testIsStripeAccountCompleteReturnsFalseWhenBothDisabled(): void
    {
        $stripeAccount = new Account();
        $stripeAccount->charges_enabled = false;
        $stripeAccount->payouts_enabled = false;

        $result = $this->service->isStripeAccountComplete($stripeAccount);

        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
