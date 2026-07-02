<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Organizers\Vat;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Organizer\Vat\OrganizerVatSettingResource;
use HiEvents\Services\Application\Handlers\Organizer\Vat\DTO\UpsertOrganizerVatSettingDTO;
use HiEvents\Services\Application\Handlers\Organizer\Vat\UpsertOrganizerVatSettingHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpsertOrganizerVatSettingAction extends BaseAction
{
    public function __construct(
        private readonly UpsertOrganizerVatSettingHandler $handler,
    ) {}

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class, Role::ADMIN);

        $validated = $request->validate([
            'vat_registered' => 'required|boolean',
            'vat_number' => 'nullable|string|max:20',
        ]);

        try {
            $vatSetting = $this->handler->handle(new UpsertOrganizerVatSettingDTO(
                organizerId: $organizerId,
                accountId: $this->getAuthenticatedAccountId(),
                vatRegistered: $validated['vat_registered'],
                vatNumber: $validated['vat_number'] ?? null,
            ));
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->resourceResponse(OrganizerVatSettingResource::class, $vatSetting);
    }
}
