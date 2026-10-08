<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\FeatureFlags;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Repository\DTO\FeatureFlagSummaryDTO;
use HiEvents\Repository\Interfaces\FeatureFlagRepositoryInterface;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Illuminate\Support\Collection;

class GetFeatureFlagsHandler
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $featureFlagRepository,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @return Collection<int, FeatureFlagSummaryDTO>
     */
    public function handle(): Collection
    {
        $configurableKeys = array_map(
            static fn (FeatureFlag $flag) => $flag->value,
            $this->featureFlagService->configurableFlags(),
        );

        return $this->featureFlagRepository->findAllWithOverrideCounts()
            ->filter(static fn (FeatureFlagSummaryDTO $flag) => in_array($flag->key, $configurableKeys, true))
            ->values();
    }
}
