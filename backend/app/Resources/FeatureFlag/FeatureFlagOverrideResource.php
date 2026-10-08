<?php

namespace HiEvents\Resources\FeatureFlag;

use HiEvents\Repository\DTO\FeatureFlagOverrideDTO;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin FeatureFlagOverrideDTO
 */
class FeatureFlagOverrideResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'account_id' => $this->accountId,
            'account_name' => $this->accountName,
            'enabled' => $this->enabled,
            'updated_at' => $this->updatedAt,
        ];
    }
}
