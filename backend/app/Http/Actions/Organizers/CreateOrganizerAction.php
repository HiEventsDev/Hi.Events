<?php

namespace HiEvents\Http\Actions\Organizers;

use Illuminate\Http\JsonResponse;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\CreateOrganizerDTO;
use HiEvents\Http\Request\Organizer\UpsertOrganizerRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Organizer\OrganizerResource;
use HiEvents\Service\Handler\Organizer\CreateOrganizerHandler;

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
