<?php

namespace HiEvents\Http\Actions\Organizers;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Organizer\UpdateOrganizerStatusRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Organizer\OrganizerResource;
use HiEvents\Services\Application\Handlers\Organizer\DTO\UpdateOrganizerStatusDTO;
use HiEvents\Services\Application\Handlers\Organizer\UpdateOrganizerStatusHandler;
use Illuminate\Http\JsonResponse;

class UpdateOrganizerStatusAction extends BaseAction
{
    public function __construct(
        private readonly UpdateOrganizerStatusHandler $updateOrganizerStatusHandler,
    )
    {
    }

    public function __invoke(UpdateOrganizerStatusRequest $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        try {
            $updatedOrganizer = $this->updateOrganizerStatusHandler->handle(UpdateOrganizerStatusDTO::fromArray([
                'status' => $request->input('status'),
                'organizerId' => $organizerId,
                'accountId' => $this->getAuthenticatedAccountId(),
            ]));
        } catch (AccountNotVerifiedException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->resourceResponse(OrganizerResource::class, $updatedOrganizer);
    }
}
