<?php

namespace Tests\Unit\Services\Domain\Organizer;

use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\BrandingRemovalNotAllowedException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;
use HiEvents\Services\Domain\Organizer\OrganizerPlanEntitlementService;
use Illuminate\Config\Repository;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class OrganizerPlanEntitlementServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OrganizerSettingsRepositoryInterface $settingsRepository;
    private OrganizerRepositoryInterface $organizerRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = Mockery::mock(OrganizerSettingsRepositoryInterface::class);
        $this->organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
    }

    private function makeService(bool $saasEnabled = true): OrganizerPlanEntitlementService
    {
        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn($saasEnabled);

        return new OrganizerPlanEntitlementService(
            organizerSettingsRepository: $this->settingsRepository,
            organizerRepository: $this->organizerRepository,
            config: $config,
        );
    }

    private function entitledPlan(): OrganizerConfigurationDomainObject
    {
        return (new OrganizerConfigurationDomainObject())->setFeatures(['branding_removal' => true]);
    }

    private function unentitledPlan(): OrganizerConfigurationDomainObject
    {
        return (new OrganizerConfigurationDomainObject())->setFeatures(null);
    }

    public function test_assert_throws_without_entitlement(): void
    {
        $this->expectException(BrandingRemovalNotAllowedException::class);

        $this->makeService()->assertCanEnableBrandingRemoval($this->unentitledPlan());
    }

    public function test_assert_throws_when_configuration_missing(): void
    {
        $this->expectException(BrandingRemovalNotAllowedException::class);

        $this->makeService()->assertCanEnableBrandingRemoval(null);
    }

    public function test_assert_passes_with_entitlement(): void
    {
        $this->makeService()->assertCanEnableBrandingRemoval($this->entitledPlan());

        $this->assertTrue(true);
    }

    public function test_assert_is_noop_when_saas_disabled(): void
    {
        $this->makeService(saasEnabled: false)->assertCanEnableBrandingRemoval(null);

        $this->assertTrue(true);
    }

    public function test_sync_clears_hide_branding_when_plan_lacks_feature(): void
    {
        $this->settingsRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(['hide_branding' => false], ['organizer_id' => 7]);

        $this->makeService()->syncOrganizerSettingsWithPlan(7, $this->unentitledPlan());
    }

    public function test_sync_enables_hide_branding_when_requested_on_entitled_plan(): void
    {
        $this->settingsRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(['hide_branding' => true], ['organizer_id' => 7]);

        $this->makeService()->syncOrganizerSettingsWithPlan(7, $this->entitledPlan(), enableEntitledFeatures: true);
    }

    public function test_sync_preserves_preference_on_entitled_plan(): void
    {
        $this->settingsRepository->shouldNotReceive('updateWhere');

        $this->makeService()->syncOrganizerSettingsWithPlan(7, $this->entitledPlan());
    }

    public function test_bulk_sync_clears_hide_branding_for_all_organizers_on_plan(): void
    {
        $configuration = $this->unentitledPlan()->setId(3);

        $this->organizerRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with(['organizer_configuration_id' => 3])
            ->andReturn(new Collection([
                (new OrganizerDomainObject())->setId(10),
                (new OrganizerDomainObject())->setId(11),
            ]));

        $this->settingsRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(['hide_branding' => false], ['organizer_id' => 10]);

        $this->settingsRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(['hide_branding' => false], ['organizer_id' => 11]);

        $this->makeService()->syncAllOrganizersOnConfiguration($configuration);
    }

    public function test_bulk_sync_is_noop_for_entitled_plan(): void
    {
        $this->organizerRepository->shouldNotReceive('findWhere');

        $this->makeService()->syncAllOrganizersOnConfiguration($this->entitledPlan()->setId(3));
    }
}
