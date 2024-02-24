<?php

namespace HiEvents\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use HiEvents\DomainObjects\AccountDomainObject;

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
            'email' => $this->getEmail(),
            'currency_code' => $this->getCurrencyCode(),
            'timezone' => $this->getTimezone(),
            'updated_at' => $this->getUpdatedAt(),
            'stripe_connect_setup_complete' => $this->getStripeConnectSetupComplete(),
            'is_account_email_confirmed' => $this->getAccountVerifiedAt() !== null,
        ];
    }
}
