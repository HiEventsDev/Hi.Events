<?php

namespace TicketKitten\Resources\Account\Stripe;

use Illuminate\Http\Resources\Json\JsonResource;
use TicketKitten\Resources\Account\AccountResource;
use TicketKitten\Service\Handler\Account\Payment\Stripe\DataTransferObjects\CreateStripeConnectAccountResponse;

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
