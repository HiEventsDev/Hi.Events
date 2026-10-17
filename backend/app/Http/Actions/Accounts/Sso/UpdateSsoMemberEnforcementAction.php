<?php

namespace HiEvents\Http\Actions\Accounts\Sso;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Http\Request\Account\Sso\UpdateSsoMemberEnforcementRequest;
use HiEvents\Resources\Account\Sso\SsoEnforcementPreviewResource;
use HiEvents\Services\Application\Handlers\Account\Sso\SsoEnforcementHandler;
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
        $this->assertEnterpriseSsoEnabled($accountId);

        try {
            $this->handler->setMemberEnforced($accountId, $accountUserId, (bool) $request->validated('sso_enforced'));
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->resourceResponse(SsoEnforcementPreviewResource::class, $this->handler->preview($accountId));
    }
}
