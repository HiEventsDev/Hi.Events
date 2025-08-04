<?php

namespace HiEvents\Http\Actions\Organizers;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Organizer\UpsertOrganizerRequest;
use HiEvents\Resources\Organizer\OrganizerResource;
use HiEvents\Services\Application\Handlers\Organizer\DTO\EditOrganizerDTO;
use HiEvents\Services\Application\Handlers\Organizer\EditOrganizerHandler;
use Illuminate\Http\JsonResponse;

class EditOrganizerAction extends BaseAction
{
    public function __construct(private readonly EditOrganizerHandler $editOrganizerHandler)
    {
    }

    public function __invoke(UpsertOrganizerRequest $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized(
            entityId: $organizerId,
            entityType: OrganizerDomainObject::class,
        );

        $organizerData = array_merge(
            $request->validated(),
            [
                'id' => $organizerId,
                'account_id' => $this->getAuthenticatedAccountId(),
            ]
        );

        $organizer = $this->editOrganizerHandler->handle(
            organizerData: EditOrganizerDTO::from($organizerData),
        );

        return $this->resourceResponse(
            resource: OrganizerResource::class,
            data: $organizer,
        );
    }
}
