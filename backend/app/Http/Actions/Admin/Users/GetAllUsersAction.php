<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Users;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\User\AdminUserResource;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllUsersDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAllUsersHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAllUsersAction extends BaseAction
{
    public function __construct(
        private readonly GetAllUsersHandler $handler,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $users = $this->handler->handle(new GetAllUsersDTO(
            perPage: min((int)$request->query('per_page', 20), 100),
            search: $request->query('search'),
        ));

        return $this->resourceResponse(
            resource: AdminUserResource::class,
            data: $users
        );
    }
}
