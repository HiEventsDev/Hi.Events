<?php

namespace Tests\Unit\Services\Application\Handlers\Organizer\Configuration;

use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\Exceptions\PlanChangeNotAllowedException;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\Configuration\UpgradeOrganizerConfigurationHandler;
use HiEvents\Services\Domain\Organizer\OrganizerPlanChangeService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class UpgradeOrganizerConfigurationHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OrganizerConfigurationRepositoryInterface $configurationRepository;
    private OrganizerPlanChangeService $planChangeService;
    private UpgradeOrganizerConfigurationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurationRepository = Mockery::mock(OrganizerConfigurationRepositoryInterface::class);
        $this->planChangeService = Mockery::mock(OrganizerPlanChangeService::class);

        $this->handler = new UpgradeOrganizerConfigurationHandler(
            configurationRepository: $this->configurationRepository,
            planChangeService: $this->planChangeService,
        );
    }

    public function test_upgrades_to_target_plan_and_enables_entitled_features(): void
    {
        $current = (new OrganizerConfigurationDomainObject())->setId(1)->setUpgradesToId(2);
        $target = (new OrganizerConfigurationDomainObject())->setId(2);
        $reloaded = (new OrganizerConfigurationDomainObject())->setId(2);

        $this->planChangeService
            ->shouldReceive('getCurrentConfiguration')
            ->once()
            ->with(5, 100)
            ->andReturn($current);

        $this->configurationRepository
            ->shouldReceive('findById')
            ->once()
            ->with(2)
            ->andReturn($target);

        $this->planChangeService
            ->shouldReceive('changeToPlan')
            ->once()
            ->with(5, $target, true);

        $this->configurationRepository
            ->shouldReceive('loadRelation')
            ->twice()
            ->andReturnSelf();

        $this->configurationRepository
            ->shouldReceive('findById')
            ->once()
            ->with(2)
            ->andReturn($reloaded);

        $this->assertSame($reloaded, $this->handler->handle(5, 100));
    }

    public function test_throws_when_fees_are_bypassed(): void
    {
        $current = (new OrganizerConfigurationDomainObject())
            ->setId(1)
            ->setUpgradesToId(2)
            ->setBypassApplicationFees(true);

        $this->planChangeService
            ->shouldReceive('getCurrentConfiguration')
            ->once()
            ->andReturn($current);

        $this->expectException(PlanChangeNotAllowedException::class);

        $this->handler->handle(5, 100);
    }

    public function test_throws_when_no_upgrade_is_available(): void
    {
        $current = (new OrganizerConfigurationDomainObject())->setId(1)->setUpgradesToId(null);

        $this->planChangeService
            ->shouldReceive('getCurrentConfiguration')
            ->once()
            ->andReturn($current);

        $this->expectException(PlanChangeNotAllowedException::class);

        $this->handler->handle(5, 100);
    }
}
