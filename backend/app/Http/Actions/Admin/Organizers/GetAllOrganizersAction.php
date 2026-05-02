<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Organizers;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Organizer\AdminOrganizerResource;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllOrganizersDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAllOrganizersHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAllOrganizersAction extends BaseAction
{
    public function __construct(
        private readonly GetAllOrganizersHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $organizers = $this->handler->handle(new GetAllOrganizersDTO(
            perPage: min((int) $request->query('per_page', 20), 100),
            search: $request->query('search'),
        ));

        return $this->resourceResponse(
            resource: AdminOrganizerResource::class,
            data: $organizers,
        );
    }
}
