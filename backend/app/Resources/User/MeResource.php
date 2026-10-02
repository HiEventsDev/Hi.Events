<?php

namespace HiEvents\Resources\User;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Illuminate\Http\Request;

/**
 * @mixin UserDomainObject
 */
class MeResource extends UserResource
{
    public function toArray(Request $request): array
    {
        $accountUser = $this->getCurrentAccountUser();
        $accountId = $accountUser?->getAccountId();
        $licence = app(LicenceService::class)->state();
        $featuresInUse = match (true) {
            $accountUser?->getRole() === Role::SUPERADMIN->name => app(LicensedFeatureUsageService::class)->featuresInUse(),
            $accountId !== null => app(LicensedFeatureUsageService::class)->featuresInUse($accountId),
            default => [],
        };

        return [
            ...parent::toArray($request),
            /** @var array<string, bool> */
            'feature_flags' => (object) ($accountId !== null
                ? app(FeatureFlagService::class)->getEnabledFlagsForAccount($accountId)
                : []),
            'licence' => [
                /** @var 'ACTIVE'|'GRACE'|'LAPSED'|'NONE'|'DEV' */
                'status' => $licence->status->value,
                'expires_at' => $licence->licence?->expires_at->toDateString(),
                'grace_ends_at' => $licence->grace_ends_at?->toDateString(),
                'invalid_reason' => $licence->invalid_reason,
                /** @var array<int, 'seating'|'box_office'|'white_label'> */
                'features_in_use' => array_map(
                    static fn (LicensedFeature $feature) => $feature->value,
                    $featuresInUse,
                ),
            ],
        ];
    }
}
