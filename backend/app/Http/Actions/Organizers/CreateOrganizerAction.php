<?php

namespace HiEvents\Http\Actions\Organizers;

use HiEvents\Exceptions\AccessRestrictedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Organizer\UpsertOrganizerRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Organizer\OrganizerResource;
use HiEvents\Services\Application\Handlers\Organizer\CreateOrganizerHandler;
use HiEvents\Services\Application\Handlers\Organizer\DTO\CreateOrganizerDTO;
use Illuminate\Http\JsonResponse;

class CreateOrganizerAction extends BaseAction
{
    public function __construct(private readonly CreateOrganizerHandler $createOrganizerHandler) {}

    public function __invoke(UpsertOrganizerRequest $request): JsonResponse
    {
        if ($this->getAuthenticatedAccess()->isRestricted()) {
            throw new AccessRestrictedException;
        }

        $organizerData = array_merge(
            $request->validated(),
            [
                'account_id' => $this->getAuthenticatedAccountId(),
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
