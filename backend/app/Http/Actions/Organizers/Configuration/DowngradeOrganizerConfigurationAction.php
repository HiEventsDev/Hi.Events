<?php

namespace HiEvents\Http\Actions\Organizers\Configuration;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\PlanChangeNotAllowedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Organizer\OrganizerConfigurationResource;
use HiEvents\Services\Application\Handlers\Organizer\Configuration\DowngradeOrganizerConfigurationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DowngradeOrganizerConfigurationAction extends BaseAction
{
    public function __construct(
        private readonly DowngradeOrganizerConfigurationHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        try {
            $configuration = $this->handler->handle($organizerId, $this->getAuthenticatedAccountId());
        } catch (PlanChangeNotAllowedException $exception) {
            throw ValidationException::withMessages([
                'plan' => $exception->getMessage(),
            ]);
        }

        return $this->jsonResponse(
            new OrganizerConfigurationResource($configuration),
            wrapInData: true,
        );
    }
}
