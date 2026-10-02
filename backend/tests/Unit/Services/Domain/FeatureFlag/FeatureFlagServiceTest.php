<?php

namespace Tests\Unit\Services\Domain\FeatureFlag;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Repository\DTO\AccountFeatureFlagDTO;
use HiEvents\Repository\Interfaces\FeatureFlagRepositoryInterface;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Mockery;
use Tests\TestCase;

class FeatureFlagServiceTest extends TestCase
{
    private const ALL_LICENSED = [LicensedFeature::SEATING, LicensedFeature::BOX_OFFICE];

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => true]);
    }

    public function test_licensed_flags_follow_the_licence_alone_off_hi_events_cloud(): void
    {
        foreach ([true, false] as $saasMode) {
            config(['app.saas_mode_enabled' => $saasMode]);
            $service = $this->serviceReturning(null, cloud: false);

            $this->assertSame(['seating' => true, 'box_office' => true], $service->getEnabledFlagsForAccount(1));
            $this->assertSame([], $service->configurableFlags());
            $this->assertTrue($service->getFlagsForAccount(1)->isEmpty());
            $service->assertEnabled(FeatureFlag::SEATING, 1);
        }
    }

    public function test_an_unlicensed_flag_is_off_off_hi_events_cloud(): void
    {
        $this->assertSame(
            ['seating' => false, 'box_office' => true],
            $this->serviceReturning(null, licensed: [LicensedFeature::BOX_OFFICE], cloud: false)->getEnabledFlagsForAccount(1),
        );
    }

    public function test_hi_events_cloud_rolls_licensed_flags_out_even_outside_saas_mode(): void
    {
        config(['app.saas_mode_enabled' => false]);
        $service = $this->serviceReturning([new AccountFeatureFlagDTO('seating', false, false)]);

        $this->assertSame([FeatureFlag::SEATING, FeatureFlag::BOX_OFFICE], $service->configurableFlags());
        $this->assertFalse($service->isEnabled(FeatureFlag::SEATING, 1));
    }

    public function test_hi_events_cloud_cannot_enable_an_unlicensed_flag(): void
    {
        $service = $this->serviceReturning([
            new AccountFeatureFlagDTO('seating', true, true),
            new AccountFeatureFlagDTO('box_office', true, null),
        ], licensed: []);

        $this->assertSame(['seating' => false, 'box_office' => false], $service->getEnabledFlagsForAccount(1));
        $this->expectException(FeatureNotEnabledException::class);
        $service->assertEnabled(FeatureFlag::SEATING, 1);
    }

    public function test_uses_default_when_account_has_no_override(): void
    {
        $service = $this->serviceReturning([new AccountFeatureFlagDTO('seating', true, null)]);

        $this->assertTrue($service->isEnabled(FeatureFlag::SEATING, 1));
    }

    public function test_enabled_override_wins_over_disabled_default(): void
    {
        $service = $this->serviceReturning([new AccountFeatureFlagDTO('seating', false, true)]);

        $this->assertTrue($service->isEnabled(FeatureFlag::SEATING, 1));
    }

    public function test_disabled_override_wins_over_enabled_default(): void
    {
        $service = $this->serviceReturning([new AccountFeatureFlagDTO('seating', true, false)]);

        $this->assertFalse($service->isEnabled(FeatureFlag::SEATING, 1));
    }

    public function test_flag_without_a_stored_row_is_disabled(): void
    {
        $service = $this->serviceReturning([]);

        $this->assertSame(['seating' => false, 'box_office' => false], $service->getEnabledFlagsForAccount(1));
    }

    public function test_unknown_stored_keys_are_ignored(): void
    {
        $service = $this->serviceReturning([
            new AccountFeatureFlagDTO('seating', true, null),
            new AccountFeatureFlagDTO('retired_flag', true, null),
        ]);

        $this->assertSame(['seating' => true, 'box_office' => false], $service->getEnabledFlagsForAccount(1));
        $this->assertSame(['seating', 'box_office'], $service->getFlagsForAccount(1)->keys()->all());
    }

    public function test_assert_enabled_throws_when_disabled(): void
    {
        $service = $this->serviceReturning([new AccountFeatureFlagDTO('seating', false, null)]);

        $this->expectException(FeatureNotEnabledException::class);

        $service->assertEnabled(FeatureFlag::SEATING, 1);
    }

    public function test_assert_enabled_passes_when_enabled(): void
    {
        $service = $this->serviceReturning([new AccountFeatureFlagDTO('seating', false, true)]);

        $service->assertEnabled(FeatureFlag::SEATING, 1);

        $this->addToAssertionCount(1);
    }

    /**
     * @param  AccountFeatureFlagDTO[]|null  $flags
     * @param  LicensedFeature[]  $licensed
     */
    private function serviceReturning(?array $flags, array $licensed = self::ALL_LICENSED, bool $cloud = true): FeatureFlagService
    {
        $repository = Mockery::mock(FeatureFlagRepositoryInterface::class);
        if ($flags === null) {
            $repository->shouldNotReceive('findForAccount');
        } else {
            $repository->shouldReceive('findForAccount')->with(1)->andReturn(collect($flags));
        }

        $licence = Mockery::mock(LicenceService::class);
        $licence->shouldReceive('allows')->andReturnUsing(
            static fn (LicensedFeature $feature) => in_array($feature, $licensed, true),
        );
        $licence->shouldReceive('isCloud')->andReturn($cloud);

        return new FeatureFlagService($repository, $licence);
    }
}
