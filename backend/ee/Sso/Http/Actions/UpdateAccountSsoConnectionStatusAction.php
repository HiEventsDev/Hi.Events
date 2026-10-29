<?php

namespace HiEvents\Enterprise\Sso\Http\Actions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Status\AccountSsoConnectionStatus;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Http\Request\UpdateAccountSsoConnectionStatusRequest;
use HiEvents\Enterprise\Sso\Resources\AccountSsoConnectionResource;
use HiEvents\Enterprise\Sso\Services\Application\Handlers\AccountSsoConnectionHandler;
use HiEvents\Http\Attributes\RequiresPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class UpdateAccountSsoConnectionStatusAction extends BaseAccountSsoAction
{
    public function __construct(
        private readonly AccountSsoConnectionHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(UpdateAccountSsoConnectionStatusRequest $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);
        $status = AccountSsoConnectionStatus::from($request->validated('status'));
        if ($status === AccountSsoConnectionStatus::ACTIVE) {
            $this->assertEnterpriseSsoEnabled($accountId);
        }

        try {
            $connection = $this->handler->setStatus($accountId, $this->getAuthenticatedUser()->getId(), $status);
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->resourceResponse(AccountSsoConnectionResource::class, $connection);
    }
}
