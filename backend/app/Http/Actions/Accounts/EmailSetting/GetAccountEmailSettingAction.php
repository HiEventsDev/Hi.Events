<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts\EmailSetting;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\AccountEmailSettingResource;
use HiEvents\Services\Application\Handlers\Account\EmailSetting\GetAccountEmailSettingHandler;
use Illuminate\Http\JsonResponse;

class GetAccountEmailSettingAction extends BaseAction
{
    public function __construct(
        private readonly GetAccountEmailSettingHandler $handler,
    ) {
    }

    public function __invoke(int $accountId): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        if ($accountId !== $this->getAuthenticatedAccountId()) {
            return $this->errorResponse(__('Unauthorized'));
        }

        $setting = $this->handler->handle($accountId);

        if (!$setting) {
            return $this->jsonResponse(['data' => null]);
        }

        return $this->resourceResponse(AccountEmailSettingResource::class, $setting);
    }
}
