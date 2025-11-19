<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts\Vat;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\AccountVatSettingResource;
use HiEvents\Services\Application\Handlers\Account\Vat\GetAccountVatSettingHandler;
use Illuminate\Http\JsonResponse;

class GetAccountVatSettingAction extends BaseAction
{
    public function __construct(
        private readonly GetAccountVatSettingHandler $handler,
    ) {
    }

    public function __invoke(int $accountId): JsonResponse
    {
        $this->minimumAllowedRole(Role::ORGANIZER);

        if ($accountId !== $this->getAuthenticatedAccountId()) {
            return $this->errorResponse(__('Unauthorized'));
        }

        $vatSetting = $this->handler->handle($accountId);

        if (!$vatSetting) {
            return $this->jsonResponse(['data' => null]);
        }

        return $this->resourceResponse(AccountVatSettingResource::class, $vatSetting);
    }
}
