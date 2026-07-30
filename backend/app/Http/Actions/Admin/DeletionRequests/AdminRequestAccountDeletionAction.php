<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\DeletionRequests;

use HiEvents\DomainObjects\Enums\AccountDeletionInitiator;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\AccountDeletionRequestResource;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class AdminRequestAccountDeletionAction extends BaseAction
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        try {
            $deletionRequest = $this->accountDeletionService->requestDeletion(
                accountId: $accountId,
                requestedByUserId: $this->getAuthenticatedUser()->getId(),
                initiator: AccountDeletionInitiator::ADMIN,
                reason: $request->input('reason'),
            );
        } catch (CannotDeleteEntityException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: HttpResponse::HTTP_CONFLICT,
            );
        }

        return $this->resourceResponse(
            resource: AccountDeletionRequestResource::class,
            data: $deletionRequest,
            statusCode: HttpResponse::HTTP_CREATED,
        );
    }
}
