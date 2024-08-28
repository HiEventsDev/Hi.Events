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
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'currency_code' => $this->getCurrencyCode(),
            'timezone' => $this->getTimezone(),
            'updated_at' => $this->getUpdatedAt(),
            'stripe_connect_setup_complete' => $this->getStripeConnectSetupComplete(),
            'is_account_email_confirmed' => $this->getAccountVerifiedAt() !== null,
            // this really should not be on the account level
            'is_saas_mode_enabled' => config('app.saas_mode_enabled'),
        ];
    }
}
