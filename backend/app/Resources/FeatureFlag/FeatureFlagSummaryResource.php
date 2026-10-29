<?php

namespace HiEvents\Resources\FeatureFlag;

use HiEvents\Repository\DTO\FeatureFlagSummaryDTO;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin FeatureFlagSummaryDTO
 */
class FeatureFlagSummaryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'enabled_by_default' => $this->enabledByDefault,
            'enabled_override_count' => $this->enabledOverrideCount,
            'disabled_override_count' => $this->disabledOverrideCount,
        ];
    }
}
