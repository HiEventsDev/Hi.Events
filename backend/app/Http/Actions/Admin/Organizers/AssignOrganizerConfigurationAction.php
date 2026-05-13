<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Organizers;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\Organizer\AssignOrganizerConfigurationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignOrganizerConfigurationAction extends BaseAction
{
    public function __construct(
        private readonly AssignOrganizerConfigurationHandler $handler,
    )
    {
    }

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $validated = $request->validate([
            'configuration_id' => 'required|integer|exists:organizer_configurations,id',
        ]);

        $this->handler->handle($organizerId, (int)$validated['configuration_id']);

        return $this->jsonResponse([
            'message' => __('Configuration assigned successfully.'),
        ]);
    }
}
