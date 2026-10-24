<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Accounts;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Admin\AdminSuspendAccountRequest;
use HiEvents\Resources\Account\AdminAccountDetailResource;
use HiEvents\Services\Application\Handlers\Admin\GetAccountHandler;
use HiEvents\Services\Domain\Account\AccountSuspensionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class SuspendAccountAction extends BaseAction
{
    public function __construct(
        private readonly AccountSuspensionService $accountSuspensionService,
        private readonly GetAccountHandler $getAccountHandler,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(AdminSuspendAccountRequest $request, int $accountId): JsonResponse
    {
        $this->requireSuperAdmin();

        try {
            $this->accountSuspensionService->suspend(
                accountId: $accountId,
                suspendedByUserId: $this->getAuthenticatedUser()->getId(),
                reason: $request->validated('reason'),
                notifyAccountOwner: $request->boolean('notify_account_owner'),
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
