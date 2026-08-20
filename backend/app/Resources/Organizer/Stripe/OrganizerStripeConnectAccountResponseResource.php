<?php

namespace HiEvents\Resources\Organizer\Stripe;

use HiEvents\Resources\Organizer\OrganizerResource;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\CreateStripeConnectAccountResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreateStripeConnectAccountResponse
 */
class OrganizerStripeConnectAccountResponseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'stripe_connect_account_type' => $this->stripeConnectAccountType,
            'stripe_account_id' => $this->stripeAccountId,
            'is_connect_setup_complete' => $this->isConnectSetupComplete,
            'connect_url' => $this->connectUrl,
            'organizer' => new OrganizerResource($this->organizer),
        ];
    }
}
