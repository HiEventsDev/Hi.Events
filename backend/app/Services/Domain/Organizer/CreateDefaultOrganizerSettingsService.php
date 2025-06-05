<?php

namespace HiEvents\Services\Domain\Organizer;

use HiEvents\DomainObjects\Enums\OrganizerHomepageVisibility;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;

class CreateDefaultOrganizerSettingsService
{
    public function __construct(
        private readonly OrganizerSettingsRepositoryInterface $organizerSettingsRepository
    )
    {
    }

    public function createOrganizerSettings(OrganizerDomainObject $organizer): void
    {
        $this->organizerSettingsRepository->create([
            'organizer_id' => $organizer->getId(),
            'homepage_visibility' => OrganizerHomepageVisibility::PUBLIC->name,

            // Use the "Modern" theme as default
            'homepage_theme_settings' => [
                'homepage_background_color' => '#2c0838',
                'homepage_content_background_color' => '#32174f',
                'homepage_primary_color' => '#c7a2db',
                'homepage_primary_text_color' => '#ffffff',
                'homepage_secondary_color' => '#c7a2db',
                'homepage_secondary_text_color' => '#ffffff',
            ],
        ]);
    }
}
