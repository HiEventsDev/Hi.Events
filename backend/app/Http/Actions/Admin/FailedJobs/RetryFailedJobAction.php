<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\FailedJobs;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\RetryFailedJobHandler;
use Illuminate\Http\JsonResponse;

class RetryFailedJobAction extends BaseAction
{
    public function __construct(
        private readonly RetryFailedJobHandler $handler,
    ) {
    }

    public function __invoke(int $jobId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $retried = $this->handler->handle($jobId);

        if (!$retried) {
            return $this->errorResponse(__('Failed job not found'), 404);
        }

        return $this->jsonResponse([
            'message' => __('Job queued for retry'),
        ]);
    }
}
