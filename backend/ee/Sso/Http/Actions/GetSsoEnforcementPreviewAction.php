<?php

namespace HiEvents\Enterprise\Sso\Http\Actions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Enterprise\Sso\Resources\SsoEnforcementPreviewResource;
use HiEvents\Enterprise\Sso\Services\Application\Handlers\SsoEnforcementHandler;
use HiEvents\Http\Attributes\RequiresPermission;
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

        return $this->resourceResponse(SsoEnforcementPreviewResource::class, $this->handler->preview($accountId));
    }
}
