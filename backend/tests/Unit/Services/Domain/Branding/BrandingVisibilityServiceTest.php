<?php

namespace Tests\Unit\Services\Domain\Branding;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Services\Domain\Branding\BrandingVisibilityService;
use Illuminate\Config\Repository;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class BrandingVisibilityServiceTest extends TestCase
{
    private function makeService(bool $saasEnabled): BrandingVisibilityService
    {
        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn($saasEnabled);

        return new BrandingVisibilityService($config);
    }

    private function makeSettings(bool $hideBranding): OrganizerSettingDomainObject
    {
        $settings = Mockery::mock(OrganizerSettingDomainObject::class);
        $settings->shouldReceive('getHideBranding')->andReturn($hideBranding);

        return $settings;
    }

    private function makeEvent(bool $hasPaidProducts): EventDomainObject
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('hasPaidProducts')->andReturn($hasPaidProducts);

        return $event;
    }

    public function test_returns_false_when_saas_disabled(): void
    {
        $service = $this->makeService(false);

        $this->assertFalse($service->shouldHideBranding($this->makeEvent(true), $this->makeSettings(true)));
    }

    public function test_returns_false_when_toggle_off(): void
    {
        $service = $this->makeService(true);

        $this->assertFalse($service->shouldHideBranding($this->makeEvent(true), $this->makeSettings(false)));
    }

    public function test_returns_false_when_settings_null(): void
    {
        $service = $this->makeService(true);

        $this->assertFalse($service->shouldHideBranding($this->makeEvent(true), null));
    }

    public function test_returns_false_for_free_event_even_when_toggle_on(): void
    {
        $service = $this->makeService(true);

        $this->assertFalse($service->shouldHideBranding($this->makeEvent(false), $this->makeSettings(true)));
    }

    public function test_returns_true_when_saas_on_toggle_on_and_paid_event(): void
    {
        $service = $this->makeService(true);

        $this->assertTrue($service->shouldHideBranding($this->makeEvent(true), $this->makeSettings(true)));
    }

    public function test_visibility_cannot_be_determined_without_organizer_settings(): void
    {
        $service = $this->makeService(true);

        $event = (new EventDomainObject())->setProducts(new Collection());

        $this->assertFalse($service->canDetermineVisibility($event));
    }

    public function test_visibility_cannot_be_determined_without_products_or_categories(): void
    {
        $service = $this->makeService(true);

        $organizer = (new OrganizerDomainObject())->setOrganizerSettings(new OrganizerSettingDomainObject());
        $event = (new EventDomainObject())->setOrganizer($organizer);

        $this->assertFalse($service->canDetermineVisibility($event));
    }

    public function test_visibility_determined_with_settings_and_products(): void
    {
        $service = $this->makeService(true);

        $organizer = (new OrganizerDomainObject())->setOrganizerSettings(new OrganizerSettingDomainObject());
        $event = (new EventDomainObject())->setOrganizer($organizer)->setProducts(new Collection());

        $this->assertTrue($service->canDetermineVisibility($event));
    }

    public function test_visibility_determined_with_settings_and_product_categories(): void
    {
        $service = $this->makeService(true);

        $organizer = (new OrganizerDomainObject())->setOrganizerSettings(new OrganizerSettingDomainObject());
        $event = (new EventDomainObject())->setOrganizer($organizer)->setProductCategories(new Collection());

        $this->assertTrue($service->canDetermineVisibility($event));
    }
}
