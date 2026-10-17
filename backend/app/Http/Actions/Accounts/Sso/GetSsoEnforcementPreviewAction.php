<?php

namespace HiEvents\Http\Actions\Accounts\Sso;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Resources\Account\Sso\SsoEnforcementPreviewResource;
use HiEvents\Services\Application\Handlers\Account\Sso\SsoEnforcementHandler;
use Illuminate\Http\JsonResponse;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class GetSsoEnforcementPreviewAction extends BaseAccountSsoAction
{
    public function __construct(
        private readonly SsoEnforcementHandler $handler,
    ) {}

    public function __invoke(int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);
        $this->assertEnterpriseSsoEnabled($accountId);

        return $this->resourceResponse(SsoEnforcementPreviewResource::class, $this->handler->preview($accountId));
    }
}
