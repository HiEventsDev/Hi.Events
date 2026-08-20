<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts\DeletionRequest;

use HiEvents\DomainObjects\Enums\AccountDeletionInitiator;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Account\RequestAccountDeletionRequest;
use HiEvents\Resources\Account\AccountDeletionRequestResource;
use HiEvents\Services\Application\Handlers\Account\DeletionRequest\DTO\RequestAccountDeletionDTO;
use HiEvents\Services\Application\Handlers\Account\DeletionRequest\RequestAccountDeletionHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class RequestAccountDeletionAction extends BaseAction
{
    public function __construct(
        private readonly RequestAccountDeletionHandler $requestAccountDeletionHandler,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(RequestAccountDeletionRequest $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        $user = $this->getAuthenticatedUser();

        if (! $user->getCurrentAccountUser()?->getIsAccountOwner()) {
            return $this->errorResponse(
                message: __('Only the account owner can request account deletion.'),
                statusCode: HttpResponse::HTTP_FORBIDDEN,
            );
        }

        try {
            $deletionRequest = $this->requestAccountDeletionHandler->handle(new RequestAccountDeletionDTO(
                accountId: $this->getAuthenticatedAccountId(),
                requestedByUserId: $user->getId(),
                initiatedBy: AccountDeletionInitiator::ACCOUNT_OWNER,
                confirmation: $request->validated('confirmation'),
                reason: $request->validated('reason'),
            ));
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
