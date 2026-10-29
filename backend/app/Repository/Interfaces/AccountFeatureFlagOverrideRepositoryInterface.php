<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AccountFeatureFlagOverrideDomainObject;
use HiEvents\Repository\DTO\FeatureFlagOverrideDTO;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<AccountFeatureFlagOverrideDomainObject>
 */
interface AccountFeatureFlagOverrideRepositoryInterface extends RepositoryInterface
{
    public function upsertOverride(int $accountId, int $featureFlagId, bool $enabled): void;

    /**
     * @return Collection<int, FeatureFlagOverrideDTO>
     */
    public function findOverridesForFlag(int $featureFlagId): Collection;
}
