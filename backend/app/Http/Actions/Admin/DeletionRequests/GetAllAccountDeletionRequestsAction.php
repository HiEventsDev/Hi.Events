<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\DeletionRequests;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Admin\AdminAccountDeletionRequestResource;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllAccountDeletionRequestsDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAllAccountDeletionRequestsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAllAccountDeletionRequestsAction extends BaseAction
{
    public function __construct(
        private readonly GetAllAccountDeletionRequestsHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $deletionRequests = $this->handler->handle(new GetAllAccountDeletionRequestsDTO(
            perPage: min((int) $request->query('per_page', 20), 100),
            search: $request->query('search'),
            status: $request->query('status'),
        ));

        return $this->resourceResponse(
            resource: AdminAccountDeletionRequestResource::class,
            data: $deletionRequests,
        );
    }
}
