<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Organizers;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Organizer\UpdateOrganizerLocationRequest;
use HiEvents\Resources\Organizer\OrganizerResource;
use HiEvents\Services\Application\Handlers\Organizer\DTO\UpdateOrganizerLocationDTO;
use HiEvents\Services\Application\Handlers\Organizer\UpdateOrganizerLocationHandler;
use Illuminate\Http\JsonResponse;

class UpdateOrganizerLocationAction extends BaseAction
{
    public function __construct(
        private readonly UpdateOrganizerLocationHandler $handler,
    ) {}

    public function __invoke(int $organizerId, UpdateOrganizerLocationRequest $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $organizer = $this->handler->handle(new UpdateOrganizerLocationDTO(
            organizer_id: $organizerId,
            account_id: $this->getAuthenticatedAccountId(),
            location_id: $request->validated('location_id'),
        ));

        return $this->resourceResponse(OrganizerResource::class, $organizer);
    }
}
