<?php

namespace HiEvents\Http\Actions\Organizers;

use Illuminate\Http\JsonResponse;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\EditOrganizerDTO;
use HiEvents\Http\Request\Organizer\UpsertOrganizerRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Organizer\OrganizerResource;
use HiEvents\Service\Handler\Organizer\EditOrganizerHandler;

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
                'account_id' => $this->getAuthenticatedUser()->getAccountId()
            ]
        );

        $organizer = $this->editOrganizerHandler->handle(
            organizerData: EditOrganizerDTO::fromArray($organizerData),
        );

        return $this->resourceResponse(
            resource: OrganizerResource::class,
            data: $organizer,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
