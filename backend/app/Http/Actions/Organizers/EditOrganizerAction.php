<?php

namespace TicketKitten\Http\Actions\Organizers;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\OrganizerDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\EditOrganizerDTO;
use TicketKitten\Http\Request\Organizer\UpsertOrganizerRequest;
use TicketKitten\Http\ResponseCodes;
use TicketKitten\Resources\Organizer\OrganizerResource;
use TicketKitten\Service\Handler\Organizer\EditOrganizerHandler;

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
