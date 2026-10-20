<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\FeatureFlags;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Generated\FeatureFlagDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\DTO\FeatureFlagOverrideDTO;
use HiEvents\Repository\Interfaces\AccountFeatureFlagOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\FeatureFlagRepositoryInterface;
use Illuminate\Support\Collection;

class GetFeatureFlagOverridesHandler
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $featureFlagRepository,
        private readonly AccountFeatureFlagOverrideRepositoryInterface $overrideRepository,
    ) {}

    /**
     * @return Collection<int, FeatureFlagOverrideDTO>
     *
     * @throws ResourceNotFoundException
     */
    public function handle(FeatureFlag $flag): Collection
    {
        $featureFlag = $this->featureFlagRepository->findFirstWhere([
            FeatureFlagDomainObjectAbstract::KEY => $flag->value,
        ]) ?? throw new ResourceNotFoundException(__('Feature flag not found'));

        return $this->overrideRepository->findOverridesForFlag($featureFlag->getId());
    }
}
