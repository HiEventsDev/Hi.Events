<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Stats;

use HiEvents\DomainObjects\Enums\AdminDashboardPeriod;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Admin\GetAdminDashboardDataRequest;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAdminDashboardDataDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAdminDashboardDataHandler;
use Illuminate\Http\JsonResponse;

class GetAdminDashboardDataAction extends BaseAction
{
    public function __construct(
        private readonly GetAdminDashboardDataHandler $handler,
    ) {}

    public function __invoke(GetAdminDashboardDataRequest $request): JsonResponse
    {
        $this->requireSuperAdmin();

        $data = $this->handler->handle(new GetAdminDashboardDataDTO(
            period: AdminDashboardPeriod::tryFrom((string) $request->validated('period')) ?? AdminDashboardPeriod::LAST_7_DAYS,
            limit: (int) ($request->validated('limit') ?? 10),
        ));

        return $this->jsonResponse($data->toArray());
    }
}
