<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\FeatureFlagDomainObject;
use HiEvents\Models\FeatureFlag;
use HiEvents\Repository\DTO\AccountFeatureFlagDTO;
use HiEvents\Repository\DTO\FeatureFlagSummaryDTO;
use HiEvents\Repository\Interfaces\FeatureFlagRepositoryInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<FeatureFlagDomainObject>
 */
class FeatureFlagRepository extends BaseRepository implements FeatureFlagRepositoryInterface
{
    protected function getModel(): string
    {
        return FeatureFlag::class;
    }

    public function getDomainObject(): string
    {
        return FeatureFlagDomainObject::class;
    }

    public function findForAccount(int $accountId): Collection
    {
        return $this->runQuery(fn () => $this->model
            ->toBase()
            ->leftJoin('account_feature_flag_overrides as o', function (JoinClause $join) use ($accountId) {
                $join->on('o.feature_flag_id', '=', 'feature_flags.id')
                    ->where('o.account_id', '=', $accountId);
            })
            ->get(['feature_flags.key', 'feature_flags.enabled_by_default', 'o.enabled as override'])
            ->map(static fn ($row) => new AccountFeatureFlagDTO(
                key: $row->key,
                enabledByDefault: (bool) $row->enabled_by_default,
                override: $row->override === null ? null : (bool) $row->override,
            )));
    }

    public function findAllWithOverrideCounts(): Collection
    {
        return $this->runQuery(fn () => $this->model
            ->toBase()
            ->leftJoin('account_feature_flag_overrides as o', 'o.feature_flag_id', '=', 'feature_flags.id')
            ->leftJoin('accounts as a', function (JoinClause $join) {
                $join->on('a.id', '=', 'o.account_id')->whereNull('a.deleted_at');
            })
            ->groupBy('feature_flags.id', 'feature_flags.key', 'feature_flags.enabled_by_default')
            ->orderBy('feature_flags.key')
            ->selectRaw('feature_flags.key, feature_flags.enabled_by_default')
            ->selectRaw('COUNT(a.id) FILTER (WHERE o.enabled = true) AS enabled_override_count')
            ->selectRaw('COUNT(a.id) FILTER (WHERE o.enabled = false) AS disabled_override_count')
            ->get()
            ->map(static fn ($row) => new FeatureFlagSummaryDTO(
                key: $row->key,
                enabledByDefault: (bool) $row->enabled_by_default,
                enabledOverrideCount: (int) $row->enabled_override_count,
                disabledOverrideCount: (int) $row->disabled_override_count,
            )));
    }
}
