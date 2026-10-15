<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts\DeletionRequest;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\AccountDeletionRequestNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Resources\Account\AccountDeletionRequestResource;
use HiEvents\Services\Application\Handlers\Account\DeletionRequest\CancelAccountDeletionHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class CancelAccountDeletionAction extends BaseAction
{
    public function __construct(
        private readonly CancelAccountDeletionHandler $cancelAccountDeletionHandler,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(): JsonResponse
    {
        try {
            $deletionRequest = $this->cancelAccountDeletionHandler->handle(
                accountId: $this->getAuthenticatedAccountId(),
                cancelledByUserId: $this->getAuthenticatedUser()->getId(),
            );
        } catch (AccountDeletionRequestNotFoundException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: HttpResponse::HTTP_NOT_FOUND,
            );
        }

        return $this->resourceResponse(AccountDeletionRequestResource::class, $deletionRequest);
    }
}
