<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\FeatureFlagDomainObject;
use HiEvents\Repository\DTO\AccountFeatureFlagDTO;
use HiEvents\Repository\DTO\FeatureFlagSummaryDTO;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<FeatureFlagDomainObject>
 */
interface FeatureFlagRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, AccountFeatureFlagDTO>
     */
    public function findForAccount(int $accountId): Collection;

    /**
     * @return Collection<int, FeatureFlagSummaryDTO>
     */
    public function findAllWithOverrideCounts(): Collection;
}
