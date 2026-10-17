<?php

namespace HiEvents\Http\Actions\Accounts\Sso;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Resources\Account\Sso\AccountSsoOverviewResource;
use HiEvents\Services\Application\Handlers\Account\Sso\AccountSsoConnectionHandler;
use Illuminate\Http\JsonResponse;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class GetAccountSsoAction extends BaseAccountSsoAction
{
    public function __construct(
        private readonly AccountSsoConnectionHandler $handler,
    ) {}

    public function __invoke(int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);
        $this->assertEnterpriseSsoEnabled($accountId);

        return $this->resourceResponse(AccountSsoOverviewResource::class, $this->handler->overview($accountId));
    }
}
