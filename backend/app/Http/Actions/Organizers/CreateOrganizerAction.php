<?php

namespace TicketKitten\Http\Actions\Organizers;

use Illuminate\Http\JsonResponse;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\CreateOrganizerDTO;
use TicketKitten\Http\Request\Organizer\UpsertOrganizerRequest;
use TicketKitten\Http\ResponseCodes;
use TicketKitten\Resources\Organizer\OrganizerResource;
use TicketKitten\Service\Handler\Organizer\CreateOrganizerHandler;

class CreateOrganizerAction extends BaseAction
{
    public function __construct(private readonly CreateOrganizerHandler $createOrganizerHandler)
    {
    }

    public function __invoke(UpsertOrganizerRequest $request): JsonResponse
    {
        $organizerData = array_merge(
            $request->validated(),
            [
                'account_id' => $this->getAuthenticatedUser()->getAccountId()
            ]
        );

        $organizer = $this->createOrganizerHandler->handle(
            organizerData: CreateOrganizerDTO::fromArray($organizerData),
        );

        return $this->resourceResponse(
            resource: OrganizerResource::class,
            data: $organizer,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
