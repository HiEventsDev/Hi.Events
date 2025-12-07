<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Accounts;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\AssignConfigurationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignConfigurationAction extends BaseAction
{
    public function __construct(
        private readonly AssignConfigurationHandler $handler,
    ) {
    }

    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $validated = $request->validate([
            'configuration_id' => 'required|integer|exists:account_configuration,id',
        ]);

        $this->handler->handle($accountId, (int) $validated['configuration_id']);

        return $this->jsonResponse([
            'message' => __('Configuration assigned successfully.'),
        ]);
    }
}
