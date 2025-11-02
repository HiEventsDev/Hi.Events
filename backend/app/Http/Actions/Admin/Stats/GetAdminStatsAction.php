<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Stats;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\GetAdminStatsHandler;
use Illuminate\Http\JsonResponse;

class GetAdminStatsAction extends BaseAction
{
    public function __construct(
        private readonly GetAdminStatsHandler $handler,
    )
    {
    }

    public function __invoke(): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $stats = $this->handler->handle();

        return $this->jsonResponse($stats->toArray());
    }
}
