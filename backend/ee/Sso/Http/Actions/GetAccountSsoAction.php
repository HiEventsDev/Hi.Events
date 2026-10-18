<?php

namespace HiEvents\Enterprise\Sso\Http\Actions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Enterprise\Sso\Resources\AccountSsoOverviewResource;
use HiEvents\Enterprise\Sso\Services\Application\Handlers\AccountSsoConnectionHandler;
use HiEvents\Http\Attributes\RequiresPermission;
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

        return $this->resourceResponse(AccountSsoOverviewResource::class, $this->handler->overview($accountId));
    }
}
