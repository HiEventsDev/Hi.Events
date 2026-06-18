<?php

namespace HiEvents\Services\Domain\Branding;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use Illuminate\Config\Repository;

class BrandingVisibilityService
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function shouldHideBranding(
        EventDomainObject $event,
        ?OrganizerSettingDomainObject $settings,
    ): bool {
        if (! $this->config->get('app.saas_mode_enabled')) {
            return false;
        }

        if (! $settings?->getHideBranding()) {
            return false;
        }

        return $event->hasPaidProducts();
    }

    public function canDetermineVisibility(EventDomainObject $event): bool
    {
        if ($event->getOrganizer()?->getOrganizerSettings() === null) {
            return false;
        }

        return $event->getProducts() !== null || $event->getProductCategories() !== null;
    }
}
