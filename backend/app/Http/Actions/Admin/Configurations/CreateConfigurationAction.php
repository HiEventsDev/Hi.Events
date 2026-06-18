<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Configurations;

use HiEvents\DomainObjects\Enums\Feature;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Resources\Organizer\OrganizerConfigurationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CreateConfigurationAction extends BaseAction
{
    public function __construct(
        private readonly OrganizerConfigurationRepositoryInterface $repository,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'application_fees' => 'required|array',
            'application_fees.fixed' => 'required|numeric|min:0',
            'application_fees.percentage' => 'required|numeric|min:0|max:100',
            'application_fees.currency' => 'sometimes|string|size:3|alpha|uppercase',
            'features' => ['sometimes', 'nullable', 'array', self::validFeatureMap()],
            'upgrades_to_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('organizer_configurations', 'id')->whereNull('deleted_at'),
            ],
            'bypass_application_fees' => 'sometimes|boolean',
        ]);

        $configuration = $this->repository->create([
            'name' => $validated['name'],
            'is_system_default' => false,
            'application_fees' => $validated['application_fees'],
            'features' => $validated['features'] ?? null,
            'upgrades_to_id' => $validated['upgrades_to_id'] ?? null,
            'bypass_application_fees' => $validated['bypass_application_fees'] ?? false,
        ]);

        return $this->jsonResponse(
            new OrganizerConfigurationResource($configuration),
            statusCode: Response::HTTP_CREATED,
            wrapInData: true
        );
    }

    public static function validFeatureMap(): callable
    {
        return static function (string $attribute, mixed $value, callable $fail) {
            $knownFeatures = array_column(Feature::cases(), 'value');

            foreach ((array) $value as $feature => $enabled) {
                if (! in_array($feature, $knownFeatures, true)) {
                    $fail(__('Unknown feature: :feature', ['feature' => $feature]));
                }

                if (! is_bool($enabled)) {
                    $fail(__('Feature values must be true or false.'));
                }
            }
        };
    }
}
