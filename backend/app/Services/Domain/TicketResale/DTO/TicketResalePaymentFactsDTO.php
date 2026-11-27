<?php

namespace HiEvents\Services\Domain\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TicketResalePaymentFactsDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $paid_online_through_stripe,
        public readonly bool $paid_on_current_stripe_account,
        public readonly bool $payment_fees_recorded,
        public readonly bool $settles_in_order_currency,
        public readonly bool $has_only_resale_refunds,
        public readonly int $card_fee,
        public readonly ?string $connected_account_id,
    ) {}
}
