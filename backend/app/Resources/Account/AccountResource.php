<?php

namespace HiEvents\Resources\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountDomainObject
 */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeStripePlatform = $this->getPrimaryStripePlatform();

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'currency_code' => $this->getCurrencyCode(),
            'timezone' => $this->getTimezone(),
            'updated_at' => $this->getUpdatedAt(),

            'is_account_email_confirmed' => $this->getAccountVerifiedAt() !== null,
            'is_saas_mode_enabled' => config('app.saas_mode_enabled'),

            $this->mergeWhen(config('app.saas_mode_enabled') && $activeStripePlatform, fn() => [
                'stripe_account_id' => $activeStripePlatform->getStripeAccountId(),
                'stripe_connect_setup_complete' => $activeStripePlatform->getStripeSetupCompletedAt() !== null,
                'stripe_account_details' => $activeStripePlatform->getStripeAccountDetails(),
                'stripe_platform' => $this->getActiveStripePlatform()?->value,
            ]),

            $this->mergeWhen($this->getConfiguration() !== null, fn() => [
                'configuration' => new AccountConfigurationResource($this->getConfiguration()),
            ]),
            'requires_manual_verification' => config('app.saas_mode_enabled') && !$this->getIsManuallyVerified(),
        ];
    }
}
