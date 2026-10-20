<?php

namespace HiEvents\Enterprise\Sso\Http\Actions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Http\Request\UpdateSsoMemberEnforcementRequest;
use HiEvents\Enterprise\Sso\Resources\SsoEnforcementPreviewResource;
use HiEvents\Enterprise\Sso\Services\Application\Handlers\SsoEnforcementHandler;
use HiEvents\Http\Attributes\RequiresPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class UpdateSsoMemberEnforcementAction extends BaseAccountSsoAction
{
    public function __construct(
        private readonly SsoEnforcementHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(UpdateSsoMemberEnforcementRequest $request, int $accountId, int $accountUserId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);
        $enforced = (bool) $request->validated('sso_enforced');
        if ($enforced) {
            $this->assertEnterpriseSsoEnabled($accountId);
        }

        try {
            $this->handler->setMemberEnforced($accountId, $accountUserId, $enforced);
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->resourceResponse(SsoEnforcementPreviewResource::class, $this->handler->preview($accountId));
    }
}
