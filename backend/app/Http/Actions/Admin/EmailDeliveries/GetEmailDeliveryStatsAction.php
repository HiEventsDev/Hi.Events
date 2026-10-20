<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\EmailDeliveries;

use HiEvents\DomainObjects\Enums\AdminDashboardPeriod;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Admin\GetEmailDeliveryStatsRequest;
use HiEvents\Resources\Admin\AdminEmailDeliveryStatsResource;
use HiEvents\Services\Application\Handlers\Admin\EmailDeliveries\GetEmailDeliveryStatsHandler;
use Illuminate\Http\JsonResponse;

class GetEmailDeliveryStatsAction extends BaseAction
{
    public function __construct(
        private readonly GetEmailDeliveryStatsHandler $handler,
    ) {}

    public function __invoke(GetEmailDeliveryStatsRequest $request): JsonResponse
    {
        $this->requireSuperAdmin();

        $stats = $this->handler->handle(
            AdminDashboardPeriod::tryFrom((string) $request->validated('period')) ?? AdminDashboardPeriod::LAST_30_DAYS,
        );

        return $this->resourceResponse(AdminEmailDeliveryStatsResource::class, $stats);
    }
}
