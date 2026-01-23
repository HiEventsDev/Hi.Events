<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Stats;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAdminDashboardDataDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAdminDashboardDataHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAdminDashboardDataAction extends BaseAction
{
    public function __construct(
        private readonly GetAdminDashboardDataHandler $handler,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $data = $this->handler->handle(new GetAdminDashboardDataDTO(
            days: min((int)$request->query('days', 14), 90),
            limit: min((int)$request->query('limit', 10), 50),
        ));

        return $this->jsonResponse($data->toArray());
    }
}
