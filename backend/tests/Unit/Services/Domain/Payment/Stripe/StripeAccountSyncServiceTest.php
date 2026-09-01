<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Generated\OrganizerStripePlatformDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerVatSettingRepositoryInterface;
use HiEvents\Services\Domain\Organizer\AssignCurrencyDefaultOrganizerConfigurationService;
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

    private AssignCurrencyDefaultOrganizerConfigurationService $assignCurrencyDefaultOrganizerConfigurationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = m::mock(LoggerInterface::class);
        $this->accountRepository = m::mock(AccountRepositoryInterface::class);
        $this->organizerRepository = m::mock(OrganizerRepositoryInterface::class);
        $this->organizerStripePlatformRepository = m::mock(OrganizerStripePlatformRepositoryInterface::class);
        $this->vatSettingRepository = m::mock(OrganizerVatSettingRepositoryInterface::class);
        $this->config = m::mock(Repository::class);
        $this->assignCurrencyDefaultOrganizerConfigurationService = m::mock(AssignCurrencyDefaultOrganizerConfigurationService::class);

        $this->service = new StripeAccountSyncService(
            $this->logger,
            $this->accountRepository,
            $this->organizerRepository,
            $this->organizerStripePlatformRepository,
            $this->vatSettingRepository,
            $this->config,
            $this->assignCurrencyDefaultOrganizerConfigurationService,
        );
    }

    public function test_is_stripe_account_complete_returns_true_when_both_enabled(): void
    {
        $stripeAccount = new Account;
        $stripeAccount->charges_enabled = true;
        $stripeAccount->payouts_enabled = true;

        $this->assertTrue($this->service->isStripeAccountComplete($stripeAccount));
    }

    public function test_is_stripe_account_complete_returns_false_when_anything_disabled(): void
    {
        foreach ([[false, true], [true, false], [false, false]] as [$charges, $payouts]) {
            $stripeAccount = new Account;
            $stripeAccount->charges_enabled = $charges;
            $stripeAccount->payouts_enabled = $payouts;
            $this->assertFalse($this->service->isStripeAccountComplete($stripeAccount));
        }
    }

    public function test_sync_by_account_id_updates_all_organizer_rows_and_stops_if_incomplete(): void
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
                m::on(fn ($attrs) => array_key_exists(OrganizerStripePlatformDomainObjectAbstract::STRIPE_SETUP_COMPLETED_AT, $attrs)
                    && $attrs[OrganizerStripePlatformDomainObjectAbstract::STRIPE_SETUP_COMPLETED_AT] === null),
                [OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => 'acct_123'],
            )
            ->andReturn(2);

        $this->service->syncStripeAccountStatusByAccountId($stripeAccount);

        $this->addToAssertionCount(1);
    }

    public function test_sync_by_account_id_assigns_currency_default_configuration_per_organizer_row(): void
    {
        $stripeAccount = Account::constructFrom([
            'id' => 'acct_123',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'country' => 'US',
            'type' => 'standard',
            'business_type' => 'individual',
            'capabilities' => [],
            'requirements' => [
                'currently_due' => [],
                'eventually_due' => [],
                'past_due' => [],
                'pending_verification' => [],
            ],
        ]);

        $this->organizerStripePlatformRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->andReturn(2);

        $rows = collect([
            (new OrganizerStripePlatformDomainObject)->setId(1)->setOrganizerId(10),
            (new OrganizerStripePlatformDomainObject)->setId(2)->setOrganizerId(20),
        ]);

        $this->organizerStripePlatformRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with([OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => 'acct_123'])
            ->andReturn($rows);

        foreach ([10 => 100, 20 => 200] as $organizerId => $accountId) {
            $this->organizerRepository
                ->shouldReceive('findById')
                ->once()
                ->with($organizerId)
                ->andReturn((new OrganizerDomainObject)->setId($organizerId)->setAccountId($accountId));

            $this->accountRepository
                ->shouldReceive('findById')
                ->once()
                ->with($accountId)
                ->andReturn(
                    (new AccountDomainObject)
                        ->setId($accountId)
                        ->setCountry('US')
                        ->setIsManuallyVerified(true)
                );
        }

        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn(false);

        $this->assignCurrencyDefaultOrganizerConfigurationService
            ->shouldReceive('assignForCountry')
            ->once()
            ->with(10, 'US');

        $this->assignCurrencyDefaultOrganizerConfigurationService
            ->shouldReceive('assignForCountry')
            ->once()
            ->with(20, 'US');

        $this->service->syncStripeAccountStatusByAccountId($stripeAccount);

        $this->addToAssertionCount(1);
    }

    public function test_mark_account_as_complete_assigns_currency_default_configuration(): void
    {
        $stripeAccount = Account::constructFrom([
            'id' => 'acct_456',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'country' => 'GB',
            'type' => 'standard',
            'business_type' => 'individual',
            'capabilities' => [],
            'requirements' => [
                'currently_due' => [],
                'eventually_due' => [],
                'past_due' => [],
                'pending_verification' => [],
            ],
        ]);

        $platform = (new OrganizerStripePlatformDomainObject)->setId(5)->setOrganizerId(50);

        $this->logger->shouldReceive('info')->once();

        $this->organizerStripePlatformRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->andReturn(1);

        $this->organizerRepository
            ->shouldReceive('findById')
            ->once()
            ->with(50)
            ->andReturn((new OrganizerDomainObject)->setId(50)->setAccountId(500));

        $this->accountRepository
            ->shouldReceive('findById')
            ->once()
            ->with(500)
            ->andReturn(
                (new AccountDomainObject)
                    ->setId(500)
                    ->setCountry('GB')
                    ->setIsManuallyVerified(true)
            );

        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn(false);

        $this->assignCurrencyDefaultOrganizerConfigurationService
            ->shouldReceive('assignForCountry')
            ->once()
            ->with(50, 'GB');

        $this->service->markAccountAsCompleteForOrganizer($platform, $stripeAccount);

        $this->addToAssertionCount(1);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
