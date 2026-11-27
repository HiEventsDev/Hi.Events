<?php

namespace HiEvents\Resources\TicketResale;

use HiEvents\Helper\Currency;
use HiEvents\Services\Application\Handlers\TicketResale\DTO\TicketResaleQuoteDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketResaleQuoteDTO
 */
class TicketResaleEstimateResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'can_list' => $this->block === null,
            /** @var 'RESALE_DISABLED'|'TICKET_TYPE_NOT_RESELLABLE'|'EVENT_NOT_LIVE'|'PRICE_TYPE_NOT_SUPPORTED'|'TICKET_TYPE_NOT_ON_SALE'|'SEATED'|'ORDER_NOT_COMPLETED'|'TICKET_NOT_ACTIVE'|'CHECKED_IN'|'TRANSFERRED'|'DATE_CANCELLED'|'DATE_PASSED'|'CUTOFF_PASSED'|'NOT_PAID_ONLINE'|'STRIPE_ACCOUNT_CHANGED'|'CURRENCY_NOT_SUPPORTED'|'ORDER_REFUNDED'|'NOTHING_TO_REFUND'|'TRANSFER_PENDING'|'ALREADY_LISTED'|null */
            'block' => $this->block?->value,
            'block_message' => $this->block?->message(),
            'currency' => $this->estimate->currency,
            'amount_paid' => Currency::fromMinorUnits($this->estimate->amount_paid, $this->estimate->currency),
            'estimated_fees' => Currency::fromMinorUnits($this->estimate->estimated_fees, $this->estimate->currency),
            'estimated_refund' => Currency::fromMinorUnits($this->estimate->estimated_refund, $this->estimate->currency),
            /** @var array<int, string> */
            'add_ons' => $this->estimate->add_ons,
            'cutoff' => $this->cutoff,
        ];
    }
}
