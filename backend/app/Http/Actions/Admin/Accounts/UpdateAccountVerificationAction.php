<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Accounts;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\AdminAccountDetailResource;
use HiEvents\Services\Application\Handlers\Admin\GetAccountHandler;
use HiEvents\Services\Application\Handlers\Admin\UpdateAccountVerificationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateAccountVerificationAction extends BaseAction
{
    public function __construct(
        private readonly UpdateAccountVerificationHandler $handler,
        private readonly GetAccountHandler $getAccountHandler,
    ) {}

    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $validated = $request->validate([
            'is_manually_verified' => 'required|boolean',
        ]);

        $this->handler->handle($accountId, $validated['is_manually_verified']);

        $account = $this->getAccountHandler->handle($accountId);

        return $this->jsonResponse(new AdminAccountDetailResource($account), wrapInData: true);
    }
}
