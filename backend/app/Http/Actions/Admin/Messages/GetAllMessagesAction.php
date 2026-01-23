<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Messages;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Resources\Admin\AdminMessageResource;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllMessagesForAdminDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAllMessagesForAdminHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAllMessagesAction extends BaseAction
{
    public function __construct(
        private readonly GetAllMessagesForAdminHandler $handler,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $messages = $this->handler->handle(new GetAllMessagesForAdminDTO(
            perPage: min((int)$request->query('per_page', 20), 100),
            search: $request->query('search'),
            status: $request->query('status'),
            type: $request->query('type'),
            sortBy: $request->query('sort_by', 'created_at'),
            sortDirection: $request->query('sort_direction', 'desc'),
        ));

        return $this->resourceResponse(
            resource: AdminMessageResource::class,
            data: $messages
        );
    }
}
