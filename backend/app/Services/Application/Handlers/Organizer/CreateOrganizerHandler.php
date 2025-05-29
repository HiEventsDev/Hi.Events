<?php

namespace HiEvents\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\Enums\OrganizerHomepageVisibility;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\CreateOrganizerDTO;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateOrganizerHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface         $organizerRepository,
        private readonly DatabaseManager                      $databaseManager,
        private readonly OrganizerSettingsRepositoryInterface $organizerSettingsRepository
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(CreateOrganizerDTO $organizerData): OrganizerDomainObject
    {
        return $this->databaseManager->transaction(
            fn() => $this->createOrganizer($organizerData)
        );
    }

    private function createOrganizer(CreateOrganizerDTO $organizerData): OrganizerDomainObject
    {
        $organizer = $this->organizerRepository->create([
            'name' => $organizerData->name,
            'email' => $organizerData->email,
            'phone' => $organizerData->phone,
            'website' => $organizerData->website,
            'description' => $organizerData->description,
            'account_id' => $organizerData->account_id,
            'timezone' => $organizerData->timezone,
            'currency' => $organizerData->currency,
        ]);

        $this->createOrganizerSettings($organizer);

        return $this->organizerRepository
            ->loadRelation(ImageDomainObject::class)
            ->findById($organizer->getId());
    }

    public function createOrganizerSettings(OrganizerDomainObject $organizer): void
    {
        $this->organizerSettingsRepository->create([
            'organizer_id' => $organizer->getId(),
            'homepage_visibility' => OrganizerHomepageVisibility::PUBLIC->name,

            'homepage_theme_settings' => [
                'homepage_background_color' => '#faf5ff',
                'homepage_content_background_color' => '#ffffff',
                'homepage_primary_color' => '#7c3aed',
                'homepage_primary_text_color' => '#1f2937',
                'homepage_secondary_color' => '#8b5cf6',
                'homepage_secondary_text_color' => '#6b7280',
            ],
        ]);
    }
}
