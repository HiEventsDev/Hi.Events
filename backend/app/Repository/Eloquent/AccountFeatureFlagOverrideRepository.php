<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountFeatureFlagOverrideDomainObject;
use HiEvents\Models\AccountFeatureFlagOverride;
use HiEvents\Repository\DTO\FeatureFlagOverrideDTO;
use HiEvents\Repository\Interfaces\AccountFeatureFlagOverrideRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<AccountFeatureFlagOverrideDomainObject>
 */
class AccountFeatureFlagOverrideRepository extends BaseRepository implements AccountFeatureFlagOverrideRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountFeatureFlagOverride::class;
    }

    public function getDomainObject(): string
    {
        return AccountFeatureFlagOverrideDomainObject::class;
    }

    public function upsertOverride(int $accountId, int $featureFlagId, bool $enabled): void
    {
        $this->runQuery(fn () => $this->model->upsert(
            [[
                'account_id' => $accountId,
                'feature_flag_id' => $featureFlagId,
                'enabled' => $enabled,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['account_id', 'feature_flag_id'],
            ['enabled', 'updated_at'],
        ));
    }

    public function findOverridesForFlag(int $featureFlagId): Collection
    {
        return $this->runQuery(fn () => $this->model
            ->toBase()
            ->join('accounts', 'accounts.id', '=', 'account_feature_flag_overrides.account_id')
            ->where('account_feature_flag_overrides.feature_flag_id', $featureFlagId)
            ->whereNull('accounts.deleted_at')
            ->orderByDesc('account_feature_flag_overrides.updated_at')
            ->get([
                'accounts.id as account_id',
                'accounts.name as account_name',
                'account_feature_flag_overrides.enabled',
                'account_feature_flag_overrides.updated_at',
            ])
            ->map(static fn ($row) => new FeatureFlagOverrideDTO(
                accountId: (int) $row->account_id,
                accountName: (string) $row->account_name,
                enabled: (bool) $row->enabled,
                updatedAt: Carbon::parse($row->updated_at)->toIso8601String(),
            )));
    }
}
