<?php

namespace HiEvents\Resources\Account\Stripe;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Application\Handlers\Account\Payment\Stripe\DTO\GetStripeConnectAccountsResponseDTO;
use HiEvents\Services\Application\Handlers\Account\Payment\Stripe\DTO\StripeConnectAccountDTO;
use Illuminate\Http\Request;

/**
 * @mixin GetStripeConnectAccountsResponseDTO
 */
class StripeConnectAccountsResponseResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'account' => [
                'id' => $this->account->getId(),
                'stripe_platform' => $this->account->getActiveStripePlatform()?->value,
            ],
            'stripe_connect_accounts' => $this->stripeConnectAccounts->map(function (StripeConnectAccountDTO $account) {
                return [
                    'stripe_account_id' => $account->stripeAccountId,
                    'connect_url' => $account->connectUrl,
                    'is_setup_complete' => $account->isSetupComplete,
                    'platform' => $account->platform?->value,
                    'account_type' => $account->accountType,
                    'is_primary' => $account->isPrimary,
                ];
            })->toArray(),
            'primary_stripe_account_id' => $this->primaryStripeAccountId,
            'has_completed_setup' => $this->hasCompletedSetup,
        ];
    }
}
