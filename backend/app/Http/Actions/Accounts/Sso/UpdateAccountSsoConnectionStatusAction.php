<?php

namespace HiEvents\Http\Actions\Accounts\Sso;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Status\AccountSsoConnectionStatus;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Http\Request\Account\Sso\UpdateAccountSsoConnectionStatusRequest;
use HiEvents\Resources\Account\Sso\AccountSsoConnectionResource;
use HiEvents\Services\Application\Handlers\Account\Sso\AccountSsoConnectionHandler;
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
        $this->assertEnterpriseSsoEnabled($accountId);

        try {
            $connection = $this->handler->setStatus($accountId, $this->getAuthenticatedUser()->getId(), AccountSsoConnectionStatus::from($request->validated('status')));
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->resourceResponse(AccountSsoConnectionResource::class, $connection);
    }
}
