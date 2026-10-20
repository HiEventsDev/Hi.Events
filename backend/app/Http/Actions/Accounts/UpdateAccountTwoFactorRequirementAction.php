<?php

namespace HiEvents\Http\Actions\Accounts;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\TwoFactorRequiredByAccountException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Http\Request\Account\UpdateAccountTwoFactorRequirementRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Account\AccountResource;
use HiEvents\Services\Application\Handlers\Account\UpdateAccountTwoFactorRequirementHandler;
use Illuminate\Http\JsonResponse;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class UpdateAccountTwoFactorRequirementAction extends BaseAction
{
    public function __construct(
        private readonly UpdateAccountTwoFactorRequirementHandler $handler,
    ) {}

    public function __invoke(UpdateAccountTwoFactorRequirementRequest $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        try {
            $account = $this->handler->handle(
                accountId: $this->getAuthenticatedAccountId(),
                actingUserId: $this->getAuthenticatedUser()->getId(),
                required: (bool) $request->validated('require_two_factor_authentication'),
            );
        } catch (TwoFactorRequiredByAccountException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(AccountResource::class, $account);
    }
}
