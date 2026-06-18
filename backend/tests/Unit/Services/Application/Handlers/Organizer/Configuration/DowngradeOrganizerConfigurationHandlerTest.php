<?php

namespace Tests\Unit\Services\Application\Handlers\Organizer\Configuration;

use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\Exceptions\PlanChangeNotAllowedException;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\Configuration\DowngradeOrganizerConfigurationHandler;
use HiEvents\Services\Domain\Organizer\OrganizerPlanChangeService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class DowngradeOrganizerConfigurationHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OrganizerConfigurationRepositoryInterface $configurationRepository;
    private OrganizerPlanChangeService $planChangeService;
    private DowngradeOrganizerConfigurationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurationRepository = Mockery::mock(OrganizerConfigurationRepositoryInterface::class);
        $this->planChangeService = Mockery::mock(OrganizerPlanChangeService::class);

        $this->handler = new DowngradeOrganizerConfigurationHandler(
            configurationRepository: $this->configurationRepository,
            planChangeService: $this->planChangeService,
        );
    }

    public function test_downgrades_to_the_plan_that_upgrades_to_current(): void
    {
        $current = (new OrganizerConfigurationDomainObject())->setId(2);
        $target = (new OrganizerConfigurationDomainObject())->setId(1);
        $currentWithOptions = (new OrganizerConfigurationDomainObject())
            ->setId(2)
            ->setDowngradeOptions(new Collection([$target]));
        $reloaded = (new OrganizerConfigurationDomainObject())->setId(1);

        $this->planChangeService
            ->shouldReceive('getCurrentConfiguration')
            ->once()
            ->with(5, 100)
            ->andReturn($current);

        $this->configurationRepository
            ->shouldReceive('loadRelation')
            ->times(3)
            ->andReturnSelf();

        $this->configurationRepository
            ->shouldReceive('findById')
            ->once()
            ->with(2)
            ->andReturn($currentWithOptions);

        $this->planChangeService
            ->shouldReceive('changeToPlan')
            ->once()
            ->with(5, $target);

        $this->configurationRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($reloaded);

        $this->assertSame($reloaded, $this->handler->handle(5, 100));
    }

    public function test_throws_when_no_downgrade_target_exists(): void
    {
        $current = (new OrganizerConfigurationDomainObject())->setId(2);
        $currentWithOptions = (new OrganizerConfigurationDomainObject())
            ->setId(2)
            ->setDowngradeOptions(new Collection());

        $this->planChangeService
            ->shouldReceive('getCurrentConfiguration')
            ->once()
            ->andReturn($current);

        $this->configurationRepository
            ->shouldReceive('loadRelation')
            ->once()
            ->andReturnSelf();

        $this->configurationRepository
            ->shouldReceive('findById')
            ->once()
            ->with(2)
            ->andReturn($currentWithOptions);

        $this->expectException(PlanChangeNotAllowedException::class);

        $this->handler->handle(5, 100);
    }

    public function test_throws_when_downgrade_target_is_ambiguous(): void
    {
        $current = (new OrganizerConfigurationDomainObject())->setId(2);
        $currentWithOptions = (new OrganizerConfigurationDomainObject())
            ->setId(2)
            ->setDowngradeOptions(new Collection([
                (new OrganizerConfigurationDomainObject())->setId(1)->setIsSystemDefault(false),
                (new OrganizerConfigurationDomainObject())->setId(3)->setIsSystemDefault(false),
            ]));

        $this->planChangeService
            ->shouldReceive('getCurrentConfiguration')
            ->once()
            ->andReturn($current);

        $this->configurationRepository
            ->shouldReceive('loadRelation')
            ->once()
            ->andReturnSelf();

        $this->configurationRepository
            ->shouldReceive('findById')
            ->once()
            ->with(2)
            ->andReturn($currentWithOptions);

        $this->expectException(PlanChangeNotAllowedException::class);

        $this->handler->handle(5, 100);
    }
}
