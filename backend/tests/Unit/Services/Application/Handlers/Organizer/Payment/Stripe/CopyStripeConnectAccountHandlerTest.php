<?php

namespace Tests\Unit\Services\Application\Handlers\Organizer\Payment\Stripe;

use Closure;
use HiEvents\DomainObjects\Generated\OrganizerStripePlatformDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SaasModeEnabledException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\CopyStripeConnectAccountHandler;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\CopyStripeConnectAccountDTO;
use HiEvents\Services\Domain\Organizer\AssignCurrencyDefaultOrganizerConfigurationService;
use HiEvents\Services\Domain\Payment\Stripe\StripeAccountSyncService;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class CopyStripeConnectAccountHandlerTest extends TestCase
{
    private OrganizerRepositoryInterface $organizerRepository;

    private OrganizerStripePlatformRepositoryInterface $organizerStripePlatformRepository;

    private StripeAccountSyncService $stripeAccountSyncService;

    private AssignCurrencyDefaultOrganizerConfigurationService $assignCurrencyDefaultOrganizerConfigurationService;

    private DatabaseManager $databaseManager;

    private Repository $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizerRepository = m::mock(OrganizerRepositoryInterface::class);
        $this->organizerStripePlatformRepository = m::mock(OrganizerStripePlatformRepositoryInterface::class);
        $this->stripeAccountSyncService = m::mock(StripeAccountSyncService::class);
        $this->stripeAccountSyncService->shouldReceive('seedVatSettingForOrganizerIfMissing')->byDefault();
        $this->assignCurrencyDefaultOrganizerConfigurationService = m::mock(AssignCurrencyDefaultOrganizerConfigurationService::class);
        $this->assignCurrencyDefaultOrganizerConfigurationService->shouldReceive('assignForCountry')->byDefault();
        $this->databaseManager = m::mock(DatabaseManager::class);
        $this->config = m::mock(Repository::class);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_copies_connection_when_saas_mode_enabled_and_source_complete(): void
    {
        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturnTrue();
        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (Closure $closure) => $closure());

        $sourcePlatform = (new OrganizerStripePlatformDomainObject)
            ->setId(11)
            ->setOrganizerId(2)
            ->setStripeAccountId('acct_source')
            ->setStripeConnectAccountType('standard')
            ->setStripeConnectPlatform('ca')
            ->setStripeSetupCompletedAt('2026-01-01 00:00:00')
            ->setStripeAccountDetails(['country' => 'CA']);

        $source = (new OrganizerDomainObject)
            ->setId(2)
            ->setAccountId(99)
            ->setName('Source');
        $source->setOrganizerStripePlatforms(collect([$sourcePlatform]));

        $target = (new OrganizerDomainObject)
            ->setId(1)
            ->setAccountId(99)
            ->setName('Target');
        $target->setOrganizerStripePlatforms(collect());

        $this->organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 1, 'account_id' => 99])
            ->andReturn($target);
        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 2, 'account_id' => 99])
            ->andReturn($source);

        $this->organizerStripePlatformRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function (array $attrs) {
                return $attrs[OrganizerStripePlatformDomainObjectAbstract::ORGANIZER_ID] === 1
                    && $attrs[OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID] === 'acct_source'
                    && $attrs[OrganizerStripePlatformDomainObjectAbstract::STRIPE_CONNECT_PLATFORM] === 'ca';
            }))
            ->andReturn(m::mock(OrganizerStripePlatformDomainObject::class));

        $this->assignCurrencyDefaultOrganizerConfigurationService
            ->shouldReceive('assignForCountry')
            ->once()
            ->with(1, 'CA');

        $handler = $this->makeHandler();

        $response = $handler->handle(new CopyStripeConnectAccountDTO(
            targetOrganizerId: 1,
            sourceOrganizerId: 2,
            accountId: 99,
        ));

        $this->assertSame('acct_source', $response->stripeAccountId);
        $this->assertTrue($response->isConnectSetupComplete);
    }

    public function test_throws_when_saas_mode_disabled(): void
    {
        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturnFalse();

        $this->expectException(SaasModeEnabledException::class);

        $this->makeHandler()->handle(new CopyStripeConnectAccountDTO(
            targetOrganizerId: 1,
            sourceOrganizerId: 2,
            accountId: 99,
        ));
    }

    public function test_throws_when_source_has_no_completed_setup(): void
    {
        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturnTrue();
        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (Closure $closure) => $closure());

        $source = (new OrganizerDomainObject)->setId(2)->setAccountId(99)->setName('Source');
        $source->setOrganizerStripePlatforms(new Collection);

        $target = (new OrganizerDomainObject)->setId(1)->setAccountId(99)->setName('Target');
        $target->setOrganizerStripePlatforms(new Collection);

        $this->organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 1, 'account_id' => 99])
            ->andReturn($target);
        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 2, 'account_id' => 99])
            ->andReturn($source);

        $this->expectException(ResourceConflictException::class);

        $this->makeHandler()->handle(new CopyStripeConnectAccountDTO(
            targetOrganizerId: 1,
            sourceOrganizerId: 2,
            accountId: 99,
        ));
    }

    public function test_throws_when_target_organizer_not_found(): void
    {
        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturnTrue();
        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (Closure $closure) => $closure());

        $this->organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 1, 'account_id' => 99])
            ->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);

        $this->makeHandler()->handle(new CopyStripeConnectAccountDTO(
            targetOrganizerId: 1,
            sourceOrganizerId: 2,
            accountId: 99,
        ));
    }

    private function makeHandler(): CopyStripeConnectAccountHandler
    {
        return new CopyStripeConnectAccountHandler(
            $this->organizerRepository,
            $this->organizerStripePlatformRepository,
            $this->stripeAccountSyncService,
            $this->assignCurrencyDefaultOrganizerConfigurationService,
            $this->databaseManager,
            $this->config,
        );
    }
}
