<?php

namespace HiEvents\Enterprise\Sso\Http\Actions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Http\Request\UpdateSsoEnforcementRequest;
use HiEvents\Enterprise\Sso\Resources\AccountSsoConnectionResource;
use HiEvents\Enterprise\Sso\Services\Application\Handlers\SsoEnforcementHandler;
use HiEvents\Http\Attributes\RequiresPermission;
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
        $enabled = (bool) $request->validated('enabled');
        if ($enabled) {
            $this->assertEnterpriseSsoEnabled($accountId);
        }

        try {
            $connection = $this->handler->update(
                accountId: $accountId,
                actorUserId: $this->getAuthenticatedUser()->getId(),
                enabled: $enabled,
                exemptAccountUserIds: $request->exemptAccountUserIds(),
            );
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->resourceResponse(AccountSsoConnectionResource::class, $connection);
    }
}
