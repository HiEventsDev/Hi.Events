<?php

namespace Tests\Unit\DomainObjects;

use HiEvents\DomainObjects\Enums\Feature;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OrganizerConfigurationDomainObjectTest extends TestCase
{
    public function test_has_feature_returns_true_when_enabled(): void
    {
        $configuration = (new OrganizerConfigurationDomainObject())
            ->setFeatures(['branding_removal' => true]);

        $this->assertTrue($configuration->hasFeature(Feature::BRANDING_REMOVAL));
    }

    public function test_has_feature_returns_false_when_disabled(): void
    {
        $configuration = (new OrganizerConfigurationDomainObject())
            ->setFeatures(['branding_removal' => false]);

        $this->assertFalse($configuration->hasFeature(Feature::BRANDING_REMOVAL));
    }

    public function test_has_feature_returns_false_when_absent_or_null(): void
    {
        $this->assertFalse(
            (new OrganizerConfigurationDomainObject())->setFeatures([])->hasFeature(Feature::BRANDING_REMOVAL)
        );
        $this->assertFalse(
            (new OrganizerConfigurationDomainObject())->setFeatures(null)->hasFeature(Feature::BRANDING_REMOVAL)
        );
    }

    public function test_has_feature_decodes_json_string_features(): void
    {
        $configuration = (new OrganizerConfigurationDomainObject())
            ->setFeatures('{"branding_removal": true}');

        $this->assertTrue($configuration->hasFeature(Feature::BRANDING_REMOVAL));
    }

    public function test_has_feature_ignores_unknown_keys(): void
    {
        $configuration = (new OrganizerConfigurationDomainObject())
            ->setFeatures(['some_future_feature' => true]);

        $this->assertFalse($configuration->hasFeature(Feature::BRANDING_REMOVAL));
    }

    public function test_downgrade_target_is_null_without_options(): void
    {
        $configuration = new OrganizerConfigurationDomainObject();

        $this->assertNull($configuration->getDowngradeTarget());
        $this->assertNull($configuration->setDowngradeOptions(new Collection())->getDowngradeTarget());
    }

    public function test_downgrade_target_returns_single_option(): void
    {
        $default = (new OrganizerConfigurationDomainObject())->setId(1);

        $configuration = (new OrganizerConfigurationDomainObject())
            ->setDowngradeOptions(new Collection([$default]));

        $this->assertSame($default, $configuration->getDowngradeTarget());
    }

    public function test_downgrade_target_prefers_system_default_among_multiple(): void
    {
        $custom = (new OrganizerConfigurationDomainObject())->setId(5)->setIsSystemDefault(false);
        $default = (new OrganizerConfigurationDomainObject())->setId(1)->setIsSystemDefault(true);

        $configuration = (new OrganizerConfigurationDomainObject())
            ->setDowngradeOptions(new Collection([$custom, $default]));

        $this->assertSame($default, $configuration->getDowngradeTarget());
    }

    public function test_downgrade_target_is_null_when_multiple_without_default(): void
    {
        $first = (new OrganizerConfigurationDomainObject())->setId(5)->setIsSystemDefault(false);
        $second = (new OrganizerConfigurationDomainObject())->setId(6)->setIsSystemDefault(false);

        $configuration = (new OrganizerConfigurationDomainObject())
            ->setDowngradeOptions(new Collection([$first, $second]));

        $this->assertNull($configuration->getDowngradeTarget());
    }
}
