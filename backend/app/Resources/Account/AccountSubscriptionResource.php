<?php

namespace HiEvents\Resources\Account;

use HiEvents\Services\Application\Handlers\Account\Subscription\DTO\AccountSubscriptionSummaryDTO;
use HiEvents\Services\Domain\Subscription\DTO\SubscriptionPriceDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountSubscriptionSummaryDTO
 */
class AccountSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'available' => $this->available,
            'active' => $this->active,
            /** @var 'INCOMPLETE'|'INCOMPLETE_EXPIRED'|'TRIALING'|'ACTIVE'|'PAST_DUE'|'CANCELED'|'UNPAID'|'PAUSED'|null */
            'status' => $this->status,
            'current_period_end' => $this->currentPeriodEnd,
            'cancel_at_period_end' => $this->cancelAtPeriodEnd,
            'prices' => array_map(fn (SubscriptionPriceDTO $price) => [
                /** @var 'month'|'year' */
                'interval' => $price->interval->value,
                'unit_amount' => $price->unitAmount,
                'currency' => $price->currency,
            ], $this->prices),
        ];
    }
}
