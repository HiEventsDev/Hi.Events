<?php

namespace Tests\Unit\Services\Application\Handlers\Organizer\Payment\Stripe;

use Closure;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SaasModeEnabledException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\CreateStripeConnectAccountHandler;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\CreateStripeConnectAccountDTO;
use HiEvents\Services\Domain\Payment\Stripe\StripeAccountSyncService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class CreateStripeConnectAccountHandlerTest extends TestCase
{
    private OrganizerRepositoryInterface $organizerRepository;

    private OrganizerStripePlatformRepositoryInterface $organizerStripePlatformRepository;

    private DatabaseManager $databaseManager;

    private LoggerInterface $logger;

    private Repository $config;

    private StripeClientFactory $stripeClientFactory;

    private StripeConfigurationService $stripeConfigurationService;

    private StripeAccountSyncService $stripeAccountSyncService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizerRepository = m::mock(OrganizerRepositoryInterface::class);
        $this->organizerStripePlatformRepository = m::mock(OrganizerStripePlatformRepositoryInterface::class);
        $this->databaseManager = m::mock(DatabaseManager::class);
        $this->logger = m::mock(LoggerInterface::class);
        $this->config = m::mock(Repository::class);
        $this->stripeClientFactory = m::mock(StripeClientFactory::class);
        $this->stripeConfigurationService = m::mock(StripeConfigurationService::class);
        $this->stripeAccountSyncService = m::mock(StripeAccountSyncService::class);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_throws_when_saas_mode_disabled(): void
    {
        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturnFalse();

        $this->expectException(SaasModeEnabledException::class);

        $this->makeHandler()->handle(new CreateStripeConnectAccountDTO(
            organizerId: 1,
            accountId: 99,
        ));
    }

    public function test_throws_resource_not_found_when_organizer_missing(): void
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

        $this->makeHandler()->handle(new CreateStripeConnectAccountDTO(
            organizerId: 1,
            accountId: 99,
        ));
    }

    private function makeHandler(): CreateStripeConnectAccountHandler
    {
        return new CreateStripeConnectAccountHandler(
            $this->organizerRepository,
            $this->organizerStripePlatformRepository,
            $this->databaseManager,
            $this->logger,
            $this->config,
            $this->stripeClientFactory,
            $this->stripeConfigurationService,
            $this->stripeAccountSyncService,
        );
    }
}
