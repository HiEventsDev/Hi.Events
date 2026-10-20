<?php

namespace Tests\Concerns;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use Illuminate\Support\Facades\DB;

trait ManagesFeatureFlags
{
    protected function setFeatureFlagDefault(FeatureFlag $flag, bool $enabled): void
    {
        DB::table('feature_flags')->updateOrInsert(
            ['key' => $flag->value],
            ['enabled_by_default' => $enabled, 'updated_at' => now()],
        );
    }

    protected function setFeatureFlagOverride(int $accountId, FeatureFlag $flag, bool $enabled): void
    {
        DB::table('feature_flags')->insertOrIgnore(['key' => $flag->value, 'enabled_by_default' => false]);

        DB::table('account_feature_flag_overrides')->updateOrInsert(
            [
                'account_id' => $accountId,
                'feature_flag_id' => DB::table('feature_flags')->where('key', $flag->value)->value('id'),
            ],
            ['enabled' => $enabled, 'updated_at' => now()],
        );
    }
}
