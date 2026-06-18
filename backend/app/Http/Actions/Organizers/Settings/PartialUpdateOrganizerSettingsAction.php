<?php

namespace HiEvents\Http\Actions\Organizers\Settings;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\BrandingRemovalNotAllowedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Organizer\Settings\PartialUpdateOrganizerSettingsRequest;
use HiEvents\Resources\Organizer\OrganizerSettingsResource;
use HiEvents\Services\Application\Handlers\Organizer\DTO\PartialUpdateOrganizerSettingsDTO;
use HiEvents\Services\Application\Handlers\Organizer\Settings\PartialUpdateOrganizerSettingsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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

        try {
            $organizerSettings = $this->handler->handle(PartialUpdateOrganizerSettingsDTO::from($request->all()));
        } catch (BrandingRemovalNotAllowedException $exception) {
            throw ValidationException::withMessages([
                'hide_branding' => $exception->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: OrganizerSettingsResource::class,
            data: $organizerSettings,
        );
    }
}
