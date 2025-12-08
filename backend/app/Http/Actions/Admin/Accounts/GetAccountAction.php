<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Accounts;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\AdminAccountDetailResource;
use HiEvents\Services\Application\Handlers\Admin\GetAccountHandler;
use Illuminate\Http\JsonResponse;

class GetAccountAction extends BaseAction
{
    public function __construct(
        private readonly GetAccountHandler $handler,
    )
    {
    }

    public function __invoke(int $accountId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $account = $this->handler->handle($accountId);

        return $this->jsonResponse(new AdminAccountDetailResource($account), wrapInData: true);
    }
}
