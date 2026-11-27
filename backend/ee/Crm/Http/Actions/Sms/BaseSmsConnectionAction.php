<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Sms;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;

abstract class BaseSmsConnectionAction extends BaseCrmAction
{
    /**
     * @throws FeatureNotEnabledException
     */
    protected function authorizeSmsChange(int $organizerId): void
    {
        $this->authorizeCrmChange($organizerId);

        app(FeatureFlagService::class)->assertEnabled(FeatureFlag::CRM_SMS, $this->getAuthenticatedAccountId());
    }
}
