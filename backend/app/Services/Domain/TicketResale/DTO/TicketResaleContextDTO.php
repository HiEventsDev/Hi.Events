<?php

namespace HiEvents\Services\Domain\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;

class TicketResaleContextDTO extends BaseDataObject
{
    public function __construct(
        public readonly EventDomainObject $event,
        public readonly OrderDomainObject $order,
        public readonly AttendeeDomainObject $attendee,
        public readonly ProductDomainObject $product,
        public readonly EventOccurrenceDomainObject $occurrence,
        public readonly bool $is_checked_in,
        public readonly bool $is_seated,
        public readonly bool $is_price_on_sale,
        public readonly bool $has_pending_transfer,
        public readonly bool $has_open_listing,
        public readonly bool $paid_online_through_stripe,
        public readonly bool $paid_on_current_stripe_account,
        public readonly bool $payment_fees_recorded,
        public readonly bool $settles_in_order_currency,
        public readonly bool $has_only_resale_refunds,
        public readonly int $seller_unit_gross,
        public readonly int $current_unit_gross,
        public readonly float $seller_unit_price,
        public readonly float $current_unit_price,
        public readonly int $seller_order_gross,
        public readonly int $seller_order_unrefunded_balance,
        public readonly int $seller_order_card_fee,
        public readonly ?string $connected_account_id,
    ) {}
}
