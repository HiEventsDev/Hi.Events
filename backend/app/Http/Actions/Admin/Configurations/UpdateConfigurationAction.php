<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Configurations;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Resources\Organizer\OrganizerConfigurationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UpdateConfigurationAction extends BaseAction
{
    public function __construct(
        private readonly OrganizerConfigurationRepositoryInterface $repository,
    ) {}

    public function __invoke(Request $request, int $configurationId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'application_fees' => 'required|array',
            'application_fees.fixed' => 'required|numeric|min:0',
            'application_fees.percentage' => 'required|numeric|min:0|max:100',
            'application_fees.currency' => 'sometimes|string|size:3|alpha|uppercase',
            'bypass_application_fees' => 'sometimes|boolean',
        ]);

        $existingConfiguration = $this->repository->findById($configurationId);
        $defaultForCurrency = $existingConfiguration->getDefaultForCurrency();
        $feeCurrency = $validated['application_fees']['currency'] ?? null;

        if ($defaultForCurrency !== null && $feeCurrency !== null && $feeCurrency !== $defaultForCurrency) {
            throw ValidationException::withMessages([
                'application_fees.currency' => __('The fee currency of the :currency default configuration must remain :currency.', [
                    'currency' => $defaultForCurrency,
                ]),
            ]);
        }

        $configuration = $this->repository->updateFromArray(
            id: $configurationId,
            attributes: [
                'name' => $validated['name'],
                'application_fees' => $validated['application_fees'],
                'bypass_application_fees' => $validated['bypass_application_fees'] ?? false,
            ]
        );

        return $this->jsonResponse(
            new OrganizerConfigurationResource($configuration),
            wrapInData: true
        );
    }
}
