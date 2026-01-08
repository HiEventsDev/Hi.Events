<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\FailedJobs;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\DeleteFailedJobHandler;
use Illuminate\Http\JsonResponse;

class DeleteFailedJobAction extends BaseAction
{
    public function __construct(
        private readonly DeleteFailedJobHandler $handler,
    ) {
    }

    public function __invoke(int $jobId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $deleted = $this->handler->handle($jobId);

        if (!$deleted) {
            return $this->errorResponse(__('Failed job not found'), 404);
        }

        return $this->deletedResponse();
    }
}
