<?php

namespace HiEvents\Resources\FeatureFlag;

use HiEvents\Repository\DTO\AccountFeatureFlagDTO;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin AccountFeatureFlagDTO
 */
class AccountFeatureFlagResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'enabled_by_default' => $this->enabledByDefault,
            'override' => $this->override,
            'enabled' => $this->isEnabled(),
        ];
    }
}
