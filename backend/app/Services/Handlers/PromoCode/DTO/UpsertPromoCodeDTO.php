<?php

namespace HiEvents\Services\Handlers\PromoCode\DTO;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;

class UpsertPromoCodeDTO
{
    public function __construct(
        public readonly string                    $code,
        public readonly int                       $event_id,
        public readonly array                     $applicable_ticket_ids,
        public readonly PromoCodeDiscountTypeEnum $discount_type,
        public readonly ?float                    $discount,
        public readonly ?string                   $expiry_date,
        public readonly ?int                      $max_allowed_usages,
    )
    {
    }
}
