<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\FeatureFlags;

use HiEvents\Repository\DTO\AccountFeatureFlagDTO;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Illuminate\Support\Collection;

class GetAccountFeatureFlagsHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @return Collection<string, AccountFeatureFlagDTO>
     */
    public function handle(int $accountId): Collection
    {
        return $this->featureFlagService->getFlagsForAccount(
            $this->accountRepository->findById($accountId)->getId()
        );
    }
}
