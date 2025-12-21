<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Configurations;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\AccountConfigurationRepositoryInterface;
use HiEvents\Resources\Account\AccountConfigurationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateConfigurationAction extends BaseAction
{
    public function __construct(
        private readonly AccountConfigurationRepositoryInterface $repository,
    ) {
    }

    public function __invoke(Request $request, int $configurationId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'application_fees' => 'required|array',
            'application_fees.fixed' => 'required|numeric|min:0',
            'application_fees.percentage' => 'required|numeric|min:0|max:100',
        ]);

        $configuration = $this->repository->updateFromArray(
            id: $configurationId,
            attributes: [
                'name' => $validated['name'],
                'application_fees' => $validated['application_fees'],
            ]
        );

        return $this->jsonResponse(
            new AccountConfigurationResource($configuration),
            wrapInData: true
        );
    }
}
