<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\BoxOffice\Http\Actions;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;

abstract class BaseBoxOfficeAction extends BaseAction
{
    /**
     * @throws FeatureNotEnabledException
     */
    protected function assertBoxOfficeEnabled(): void
    {
        app(FeatureFlagService::class)->assertEnabled(FeatureFlag::BOX_OFFICE, $this->getAuthenticatedAccountId());
    }
}
