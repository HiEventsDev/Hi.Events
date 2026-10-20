<?php

namespace HiEvents\Resources\Organizer\Stripe;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\GetStripeConnectAccountsResponseDTO;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\ReusableStripeConnectionDTO;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\StripeConnectAccountDTO;
use Illuminate\Http\Request;

/**
 * @mixin GetStripeConnectAccountsResponseDTO
 */
class OrganizerStripeConnectAccountsResponseResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'organizer' => [
                'id' => $this->organizer->getId(),
                'name' => $this->organizer->getName(),
                'stripe_platform' => $this->organizer->getActiveStripePlatform()?->value,
            ],
            'stripe_connect_accounts' => $this->stripeConnectAccounts->map(function (StripeConnectAccountDTO $account) {
                return [
                    'stripe_account_id' => $account->stripeAccountId,
                    'connect_url' => $account->connectUrl,
                    'is_setup_complete' => $account->isSetupComplete,
                    'platform' => $account->platform?->value,
                    'account_type' => $account->accountType,
                    'is_primary' => $account->isPrimary,
                    'country' => $account->country,
                    'business_type' => $account->businessType,
                    'charges_enabled' => $account->chargesEnabled,
                    'payouts_enabled' => $account->payoutsEnabled,
                    'capabilities' => $account->capabilities,
                    'requirements' => $account->requirements,
                ];
            })->values()->toArray(),
            'reusable_connections' => $this->reusableConnections->map(function (ReusableStripeConnectionDTO $connection) {
                return [
                    'organizer_id' => $connection->organizerId,
                    'organizer_name' => $connection->organizerName,
                    'stripe_account_id' => $connection->stripeAccountId,
                    'platform' => $connection->platform,
                    'country' => $connection->country,
                    'business_type' => $connection->businessType,
                ];
            })->values()->toArray(),
            'primary_stripe_account_id' => $this->primaryStripeAccountId,
            'has_completed_setup' => $this->hasCompletedSetup,
        ];
    }
}
