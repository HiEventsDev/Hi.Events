<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\FailedJobs;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\RetryFailedJobHandler;
use Illuminate\Http\JsonResponse;

class RetryAllFailedJobsAction extends BaseAction
{
    public function __construct(
        private readonly RetryFailedJobHandler $handler,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $count = $this->handler->retryAll();

        return $this->jsonResponse([
            'message' => __('Queued :count jobs for retry', ['count' => $count]),
            'retry_count' => $count,
        ]);
    }
}
