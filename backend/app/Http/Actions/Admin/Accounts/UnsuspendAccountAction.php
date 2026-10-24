<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Accounts;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\AdminAccountDetailResource;
use HiEvents\Services\Application\Handlers\Admin\GetAccountHandler;
use HiEvents\Services\Domain\Account\AccountSuspensionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class UnsuspendAccountAction extends BaseAction
{
    public function __construct(
        private readonly AccountSuspensionService $accountSuspensionService,
        private readonly GetAccountHandler $getAccountHandler,
    ) {}

    public function __invoke(int $accountId): JsonResponse
    {
        $this->requireSuperAdmin();

        try {
            $this->accountSuspensionService->unsuspend(
                accountId: $accountId,
                unsuspendedByUserId: $this->getAuthenticatedUser()->getId(),
            );
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: HttpResponse::HTTP_CONFLICT,
            );
        }

        return $this->jsonResponse(new AdminAccountDetailResource($this->getAccountHandler->handle($accountId)), wrapInData: true);
    }
}
