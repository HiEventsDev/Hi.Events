<?php

namespace HiEvents\Http\Actions\Accounts\Sso;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Http\Request\Account\Sso\UpdateSsoEnforcementRequest;
use HiEvents\Resources\Account\Sso\AccountSsoConnectionResource;
use HiEvents\Services\Application\Handlers\Account\Sso\SsoEnforcementHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class UpdateSsoEnforcementAction extends BaseAccountSsoAction
{
    public function __construct(
        private readonly SsoEnforcementHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function __invoke(UpdateSsoEnforcementRequest $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);
        $this->assertEnterpriseSsoEnabled($accountId);

        try {
            $connection = $this->handler->update(
                accountId: $accountId,
                actorUserId: $this->getAuthenticatedUser()->getId(),
                enabled: (bool) $request->validated('enabled'),
                exemptAccountUserIds: $request->exemptAccountUserIds(),
            );
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->resourceResponse(AccountSsoConnectionResource::class, $connection);
    }
}
