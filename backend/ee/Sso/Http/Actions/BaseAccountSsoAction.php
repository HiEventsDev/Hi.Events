<?php

namespace HiEvents\Enterprise\Sso\Http\Actions;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Illuminate\Validation\ValidationException;

abstract class BaseAccountSsoAction extends BaseAction
{
    /**
     * @throws FeatureNotEnabledException
     */
    protected function assertEnterpriseSsoEnabled(int $accountId): void
    {
        app(FeatureFlagService::class)->assertEnabled(FeatureFlag::ENTERPRISE_SSO, $accountId);
    }

    protected function configurationError(AccountSsoConfigurationException $exception): ValidationException
    {
        return ValidationException::withMessages([$exception->field => $exception->getMessage()]);
    }
}
