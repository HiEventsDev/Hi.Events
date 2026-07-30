<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\DeletionRequests;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\AccountDeletionRequestStatus;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Jobs\Account\ExecuteAccountDeletionJob;
use HiEvents\Repository\Interfaces\AccountDeletionRequestRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AdminExecuteAccountDeletionAction extends BaseAction
{
    public function __construct(
        private readonly AccountDeletionRequestRepositoryInterface $deletionRequestRepository,
    ) {}

    public function __invoke(int $deletionRequestId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $deletionRequest = $this->deletionRequestRepository->findById($deletionRequestId);

        if ($deletionRequest->getStatus() !== AccountDeletionRequestStatus::REQUESTED->name) {
            return $this->errorResponse(
                message: __('Only pending deletion requests can be executed.'),
                statusCode: HttpResponse::HTTP_CONFLICT,
            );
        }

        ExecuteAccountDeletionJob::dispatch($deletionRequestId);

        return $this->jsonResponse([
            'message' => __('Account deletion has been queued for execution.'),
        ]);
    }
}
