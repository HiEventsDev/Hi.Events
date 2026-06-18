<?php

namespace HiEvents\Services\Domain\Organizer;

use HiEvents\DomainObjects\Enums\Feature;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\BrandingRemovalNotAllowedException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;
use Illuminate\Config\Repository;

class OrganizerPlanEntitlementService
{
    public function __construct(
        private readonly OrganizerSettingsRepositoryInterface $organizerSettingsRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly Repository $config,
    ) {}

    /**
     * @throws BrandingRemovalNotAllowedException
     */
    public function assertCanEnableBrandingRemoval(?OrganizerConfigurationDomainObject $configuration): void
    {
        if (! $this->config->get('app.saas_mode_enabled')) {
            return;
        }

        if (! $configuration?->hasFeature(Feature::BRANDING_REMOVAL)) {
            throw new BrandingRemovalNotAllowedException(
                __('Branding removal is not included in your plan.'),
            );
        }
    }

    public function syncOrganizerSettingsWithPlan(
        int $organizerId,
        OrganizerConfigurationDomainObject $newConfiguration,
        bool $enableEntitledFeatures = false,
    ): void {
        if (! $newConfiguration->hasFeature(Feature::BRANDING_REMOVAL)) {
            $this->setHideBranding($organizerId, false);

            return;
        }

        if ($enableEntitledFeatures) {
            $this->setHideBranding($organizerId, true);
        }
    }

    public function syncAllOrganizersOnConfiguration(OrganizerConfigurationDomainObject $configuration): void
    {
        if ($configuration->hasFeature(Feature::BRANDING_REMOVAL)) {
            return;
        }

        $this->organizerRepository
            ->findWhere(['organizer_configuration_id' => $configuration->getId()])
            ->each(fn (OrganizerDomainObject $organizer) => $this->setHideBranding($organizer->getId(), false));
    }

    private function setHideBranding(int $organizerId, bool $hideBranding): void
    {
        $this->organizerSettingsRepository->updateWhere(
            attributes: ['hide_branding' => $hideBranding],
            where: ['organizer_id' => $organizerId],
        );
    }
}
