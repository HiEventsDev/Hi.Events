<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\FeatureFlag;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Repository\DTO\AccountFeatureFlagDTO;
use HiEvents\Repository\Interfaces\FeatureFlagRepositoryInterface;
use Illuminate\Support\Collection;

class FeatureFlagService
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $featureFlagRepository,
        private readonly LicenceService $licenceService,
    ) {}

    /**
     * @return FeatureFlag[]
     */
    public function configurableFlags(): array
    {
        return array_values(array_filter(
            FeatureFlag::cases(),
            fn (FeatureFlag $flag) => $flag->licensedFeature() === null
                ? (bool) config('app.saas_mode_enabled')
                : $this->licenceService->isCloud(),
        ));
    }

    /**
     * @return Collection<string, AccountFeatureFlagDTO>
     */
    public function getFlagsForAccount(int $accountId): Collection
    {
        $configurable = $this->configurableFlags();

        if ($configurable === []) {
            return collect();
        }

        $stored = $this->featureFlagRepository->findForAccount($accountId)->keyBy('key');

        return collect($configurable)->mapWithKeys(static fn (FeatureFlag $flag) => [
            $flag->value => $stored->get($flag->value) ?? new AccountFeatureFlagDTO(
                key: $flag->value,
                enabledByDefault: false,
                override: null,
            ),
        ]);
    }

    /**
     * @return array<string, bool>
     */
    public function getEnabledFlagsForAccount(int $accountId): array
    {
        $configured = $this->getFlagsForAccount($accountId);

        return collect(FeatureFlag::cases())
            ->mapWithKeys(fn (FeatureFlag $flag) => [
                $flag->value => $this->isLicensed($flag) && ($configured->get($flag->value)?->isEnabled() ?? true),
            ])
            ->all();
    }

    public function isEnabled(FeatureFlag $flag, int $accountId): bool
    {
        return $this->getEnabledFlagsForAccount($accountId)[$flag->value];
    }

    /**
     * @throws FeatureNotEnabledException
     */
    public function assertEnabled(FeatureFlag $flag, int $accountId): void
    {
        if (! $this->isEnabled($flag, $accountId)) {
            throw new FeatureNotEnabledException;
        }
    }

    private function isLicensed(FeatureFlag $flag): bool
    {
        $licensedFeature = $flag->licensedFeature();

        return $licensedFeature === null || $this->licenceService->allows($licensedFeature);
    }
}
