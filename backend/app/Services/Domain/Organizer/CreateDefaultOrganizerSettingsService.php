<?php

namespace HiEvents\Services\Domain\Organizer;

use HiEvents\DomainObjects\Enums\AttendeeDetailsCollectionMethod;
use HiEvents\DomainObjects\Enums\ColorTheme;
use HiEvents\DomainObjects\Enums\OrganizerHomepageVisibility;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;

class CreateDefaultOrganizerSettingsService
{
    public function __construct(
        private readonly OrganizerSettingsRepositoryInterface $organizerSettingsRepository
    ) {}

    public function createOrganizerSettings(OrganizerDomainObject $organizer): void
    {
        /** @var ColorTheme $defaultTheme */
        $defaultTheme = config('app.organizer_homepage_default_theme');

        $this->organizerSettingsRepository->create([
            'organizer_id' => $organizer->getId(),
            'homepage_visibility' => OrganizerHomepageVisibility::PUBLIC->name,
            'homepage_theme_settings' => $defaultTheme->getThemeData(),
            'default_attendee_details_collection_method' => AttendeeDetailsCollectionMethod::PER_ORDER->name,
            'default_pass_platform_fee_to_buyer' => config('app.saas_default_pass_platform_fee_to_buyer', true),
        ]);
    }
}
