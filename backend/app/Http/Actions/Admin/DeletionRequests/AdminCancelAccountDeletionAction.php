<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\DeletionRequests;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\AccountDeletionRequestNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\AccountDeletionRequestRepositoryInterface;
use HiEvents\Resources\Account\AccountDeletionRequestResource;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class AdminCancelAccountDeletionAction extends BaseAction
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
        private readonly AccountDeletionRequestRepositoryInterface $deletionRequestRepository,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(int $deletionRequestId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $deletionRequest = $this->deletionRequestRepository->findById($deletionRequestId);

        try {
            $cancelledRequest = $this->accountDeletionService->cancelDeletion(
                accountId: $deletionRequest->getAccountId(),
                cancelledByUserId: $this->getAuthenticatedUser()->getId(),
            );
        } catch (AccountDeletionRequestNotFoundException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: HttpResponse::HTTP_NOT_FOUND,
            );
        }

        return $this->resourceResponse(AccountDeletionRequestResource::class, $cancelledRequest);
    }
}
