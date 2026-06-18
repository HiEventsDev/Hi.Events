<?php

namespace Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\Organizer\AssignOrganizerConfigurationHandler;
use HiEvents\Services\Domain\Organizer\OrganizerPlanEntitlementService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class AssignOrganizerConfigurationHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_assignment_updates_organizer_and_syncs_entitlements(): void
    {
        $configuration = (new OrganizerConfigurationDomainObject())->setId(2);

        $organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        $configurationRepository = Mockery::mock(OrganizerConfigurationRepositoryInterface::class);
        $entitlementService = Mockery::mock(OrganizerPlanEntitlementService::class);
        $databaseManager = Mockery::mock(DatabaseManager::class);

        $configurationRepository
            ->shouldReceive('findById')
            ->once()
            ->with(2)
            ->andReturn($configuration);

        $databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $organizerRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(7, ['organizer_configuration_id' => 2]);

        $entitlementService
            ->shouldReceive('syncOrganizerSettingsWithPlan')
            ->once()
            ->with(7, $configuration);

        $handler = new AssignOrganizerConfigurationHandler(
            organizerRepository: $organizerRepository,
            configurationRepository: $configurationRepository,
            entitlementService: $entitlementService,
            databaseManager: $databaseManager,
        );

        $handler->handle(7, 2);
    }
}
