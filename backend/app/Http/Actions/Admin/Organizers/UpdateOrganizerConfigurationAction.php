<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Organizers;

use HiEvents\DataTransferObjects\UpdateOrganizerConfigurationDTO;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Organizer\OrganizerConfigurationResource;
use HiEvents\Services\Application\Handlers\Admin\Organizer\UpdateOrganizerConfigurationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateOrganizerConfigurationAction extends BaseAction
{
    public function __construct(
        private readonly UpdateOrganizerConfigurationHandler $handler,
    ) {}

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $validated = $request->validate([
            'application_fees' => 'required|array',
            'application_fees.fixed' => 'required|numeric|min:0',
            'application_fees.percentage' => 'required|numeric|min:0|max:100',
            'application_fees.currency' => 'nullable|string|size:3',
        ]);

        $configuration = $this->handler->handle(new UpdateOrganizerConfigurationDTO(
            organizerId: $organizerId,
            applicationFees: $validated['application_fees'],
        ));

        return $this->resourceResponse(
            resource: OrganizerConfigurationResource::class,
            data: $configuration,
        );
    }
}
