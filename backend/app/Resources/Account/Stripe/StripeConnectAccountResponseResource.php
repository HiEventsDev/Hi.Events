<?php

namespace HiEvents\Resources\Account\Stripe;

use Illuminate\Http\Resources\Json\JsonResource;
use HiEvents\Resources\Account\AccountResource;
use HiEvents\Services\Handlers\Account\Payment\Stripe\DTO\CreateStripeConnectAccountResponse;

/**
 * @mixin CreateStripeConnectAccountResponse
 */
class StripeConnectAccountResponseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'stripe_account_id' => $this->stripeAccountId,
            'is_connect_setup_complete' => $this->isConnectSetupComplete,
            'connect_url' => $this->connectUrl,
            'account' => new AccountResource($this->account),
        ];
    }
}
