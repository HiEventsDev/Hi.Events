<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Accounts;

use HiEvents\DataTransferObjects\UpdateAdminAccountVatSettingDTO;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\AccountVatSettingResource;
use HiEvents\Services\Application\Handlers\Admin\UpdateAdminAccountVatSettingHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateAccountVatSettingAction extends BaseAction
{
    public function __construct(
        private readonly UpdateAdminAccountVatSettingHandler $handler,
    )
    {
    }

    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $validated = $request->validate([
            'vat_registered' => 'required|boolean',
            'vat_number' => 'nullable|string|max:20',
            'vat_validated' => 'nullable|boolean',
            'business_name' => 'nullable|string|max:255',
            'business_address' => 'nullable|string|max:500',
            'vat_country_code' => 'nullable|string|max:2',
        ]);

        $vatSetting = $this->handler->handle(new UpdateAdminAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: $validated['vat_registered'],
            vatNumber: $validated['vat_number'] ?? null,
            vatValidated: $validated['vat_validated'] ?? null,
            businessName: $validated['business_name'] ?? null,
            businessAddress: $validated['business_address'] ?? null,
            vatCountryCode: $validated['vat_country_code'] ?? null,
        ));

        return $this->resourceResponse(
            resource: AccountVatSettingResource::class,
            data: $vatSetting
        );
    }
}
