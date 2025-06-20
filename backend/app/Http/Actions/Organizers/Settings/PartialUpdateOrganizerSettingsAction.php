<?php

namespace HiEvents\Http\Actions\Organizers\Settings;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Organizer\Settings\PartialUpdateOrganizerSettingsRequest;
use HiEvents\Resources\Organizer\OrganizerSettingsResource;
use HiEvents\Services\Application\Handlers\Organizer\DTO\PartialUpdateOrganizerSettingsDTO;
use HiEvents\Services\Application\Handlers\Organizer\Settings\PartialUpdateOrganizerSettingsHandler;
use Illuminate\Http\JsonResponse;

class PartialUpdateOrganizerSettingsAction extends BaseAction
{
    public function __construct(
        private readonly PartialUpdateOrganizerSettingsHandler $handler,
    )
    {
    }

    public function __invoke(PartialUpdateOrganizerSettingsRequest $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $request->merge([
            'accountId' => $this->getAuthenticatedAccountId(),
            'organizerId' => $organizerId,
        ]);

        $organizerSettings = $this->handler->handle(PartialUpdateOrganizerSettingsDTO::from($request->all()));

        return $this->resourceResponse(
            resource: OrganizerSettingsResource::class,
            data: $organizerSettings,
        );
    }
}
