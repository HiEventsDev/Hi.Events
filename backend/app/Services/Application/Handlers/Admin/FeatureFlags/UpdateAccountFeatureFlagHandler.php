<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\FeatureFlags;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Generated\AccountFeatureFlagOverrideDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\FeatureFlagDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\DTO\AccountFeatureFlagDTO;
use HiEvents\Repository\Interfaces\AccountFeatureFlagOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\FeatureFlagRepositoryInterface;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Illuminate\Support\Collection;

class UpdateAccountFeatureFlagHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly FeatureFlagRepositoryInterface $featureFlagRepository,
        private readonly AccountFeatureFlagOverrideRepositoryInterface $overrideRepository,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @return Collection<string, AccountFeatureFlagDTO>
     *
     * @throws ResourceNotFoundException
     */
    public function handle(int $accountId, FeatureFlag $flag, ?bool $enabled): Collection
    {
        $account = $this->accountRepository->findById($accountId);

        $featureFlag = $this->featureFlagRepository->findFirstWhere([
            FeatureFlagDomainObjectAbstract::KEY => $flag->value,
        ]) ?? throw new ResourceNotFoundException(__('Feature flag not found'));

        if ($enabled === null) {
            $this->overrideRepository->deleteWhere([
                AccountFeatureFlagOverrideDomainObjectAbstract::ACCOUNT_ID => $account->getId(),
                AccountFeatureFlagOverrideDomainObjectAbstract::FEATURE_FLAG_ID => $featureFlag->getId(),
            ]);
        } else {
            $this->overrideRepository->upsertOverride($account->getId(), $featureFlag->getId(), $enabled);
        }

        return $this->featureFlagService->getFlagsForAccount($account->getId());
    }
}
