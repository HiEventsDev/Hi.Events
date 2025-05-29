<?php

namespace HiEvents\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\EditOrganizerDTO;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class EditOrganizerHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly DatabaseManager              $databaseManager,
        private readonly HtmlPurifierService          $htmlPurifierService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(EditOrganizerDTO $organizerData): OrganizerDomainObject
    {
        return $this->databaseManager->transaction(
            fn() => $this->editOrganizer($organizerData)
        );
    }

    private function editOrganizer(EditOrganizerDTO $organizerData): OrganizerDomainObject
    {
        $this->organizerRepository->updateWhere(
            attributes: [
                'name' => $organizerData->name,
                'email' => $organizerData->email,
                'phone' => $organizerData->phone,
                'website' => $organizerData->website,
                'description' => $this->htmlPurifierService->purify($organizerData->description),
                'account_id' => $organizerData->account_id,
                'timezone' => $organizerData->timezone,
                'currency' => $organizerData->currency,
            ],
            where: [
                'id' => $organizerData->id,
                'account_id' => $organizerData->account_id,
            ]
        );

        return $this->organizerRepository
            ->loadRelation(ImageDomainObject::class)
            ->findById($organizerData->id);
    }
}
