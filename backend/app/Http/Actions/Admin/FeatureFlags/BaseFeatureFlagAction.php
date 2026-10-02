<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\FeatureFlags;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;

abstract class BaseFeatureFlagAction extends BaseAction
{
    /**
     * @throws ResourceNotFoundException
     */
    protected function authorizeAndResolveFlag(string $key): FeatureFlag
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        return FeatureFlag::tryFrom($key)
            ?? throw new ResourceNotFoundException(__('Feature flag not found'));
    }
}
