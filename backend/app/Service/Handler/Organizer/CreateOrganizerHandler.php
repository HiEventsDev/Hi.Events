<?php

namespace TicketKitten\Service\Handler\Organizer;

use Illuminate\Database\DatabaseManager;
use Throwable;
use TicketKitten\DomainObjects\Enums\OrganizerImageType;
use TicketKitten\DomainObjects\ImageDomainObject;
use TicketKitten\DomainObjects\OrganizerDomainObject;
use TicketKitten\Http\DataTransferObjects\CreateOrganizerDTO;
use TicketKitten\Repository\Interfaces\OrganizerRepositoryInterface;
use TicketKitten\Service\Common\Image\ImageUploadService;

readonly class CreateOrganizerHandler
{
    public function __construct(
        private OrganizerRepositoryInterface $organizerRepository,
        private ImageUploadService           $imageUploadService,
        private DatabaseManager              $databaseManager,
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

        if ($organizerData->logo !== null) {
            $this->imageUploadService->upload(
                image: $organizerData->logo,
                entityId: $organizer->getId(),
                entityType: OrganizerDomainObject::class,
                imageType: OrganizerImageType::LOGO->name,
            );
        }

        return $this->organizerRepository
            ->loadRelation(ImageDomainObject::class)
            ->findById($organizer->getId());
    }
}
