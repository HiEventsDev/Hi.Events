<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\Generated\OrganizerStripePlatformDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerVatSettingRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
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
    private OrganizerRepositoryInterface $organizerRepository;
    private OrganizerStripePlatformRepositoryInterface $organizerStripePlatformRepository;
    private OrganizerVatSettingRepositoryInterface $vatSettingRepository;
    private Repository $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = m::mock(LoggerInterface::class);
        $this->accountRepository = m::mock(AccountRepositoryInterface::class);
        $this->organizerRepository = m::mock(OrganizerRepositoryInterface::class);
        $this->organizerStripePlatformRepository = m::mock(OrganizerStripePlatformRepositoryInterface::class);
        $this->vatSettingRepository = m::mock(OrganizerVatSettingRepositoryInterface::class);
        $this->config = m::mock(Repository::class);

        $this->service = new StripeAccountSyncService(
            $this->logger,
            $this->accountRepository,
            $this->organizerRepository,
            $this->organizerStripePlatformRepository,
            $this->vatSettingRepository,
            $this->config,
        );
    }

    public function testIsStripeAccountCompleteReturnsTrueWhenBothEnabled(): void
    {
        $stripeAccount = new Account();
        $stripeAccount->charges_enabled = true;
        $stripeAccount->payouts_enabled = true;

        $this->assertTrue($this->service->isStripeAccountComplete($stripeAccount));
    }

    public function testIsStripeAccountCompleteReturnsFalseWhenAnythingDisabled(): void
    {
        foreach ([[false, true], [true, false], [false, false]] as [$charges, $payouts]) {
            $stripeAccount = new Account();
            $stripeAccount->charges_enabled = $charges;
            $stripeAccount->payouts_enabled = $payouts;
            $this->assertFalse($this->service->isStripeAccountComplete($stripeAccount));
        }
    }

    public function testSyncByAccountIdUpdatesAllOrganizerRowsAndStopsIfIncomplete(): void
    {
        $stripeAccount = Account::constructFrom([
            'id' => 'acct_123',
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'country' => 'US',
            'type' => 'standard',
            'business_type' => 'individual',
            'capabilities' => [],
            'requirements' => [
                'currently_due' => ['external_account'],
                'eventually_due' => [],
                'past_due' => [],
                'pending_verification' => [],
            ],
        ]);

        $this->organizerStripePlatformRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                m::on(fn($attrs) => array_key_exists(OrganizerStripePlatformDomainObjectAbstract::STRIPE_SETUP_COMPLETED_AT, $attrs)
                    && $attrs[OrganizerStripePlatformDomainObjectAbstract::STRIPE_SETUP_COMPLETED_AT] === null),
                [OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => 'acct_123'],
            )
            ->andReturn(2);

        $this->service->syncStripeAccountStatusByAccountId($stripeAccount);

        $this->addToAssertionCount(1);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
