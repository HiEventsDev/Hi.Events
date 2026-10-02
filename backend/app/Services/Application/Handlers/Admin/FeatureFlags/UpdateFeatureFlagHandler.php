<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\FeatureFlags;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Generated\FeatureFlagDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\DTO\FeatureFlagSummaryDTO;
use HiEvents\Repository\Interfaces\FeatureFlagRepositoryInterface;

class UpdateFeatureFlagHandler
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $featureFlagRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(FeatureFlag $flag, bool $enabledByDefault): FeatureFlagSummaryDTO
    {
        $this->featureFlagRepository->updateWhere(
            attributes: [FeatureFlagDomainObjectAbstract::ENABLED_BY_DEFAULT => $enabledByDefault],
            where: [FeatureFlagDomainObjectAbstract::KEY => $flag->value],
        );

        return $this->featureFlagRepository->findAllWithOverrideCounts()
            ->firstWhere('key', $flag->value)
            ?? throw new ResourceNotFoundException(__('Feature flag not found'));
    }
}
