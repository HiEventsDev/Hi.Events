<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\FailedJobs;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\DeleteFailedJobHandler;
use Illuminate\Http\JsonResponse;

class DeleteAllFailedJobsAction extends BaseAction
{
    public function __construct(
        private readonly DeleteFailedJobHandler $handler,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $count = $this->handler->deleteAll();

        return $this->jsonResponse([
            'message' => __('Deleted :count failed jobs', ['count' => $count]),
            'deleted_count' => $count,
        ]);
    }
}
