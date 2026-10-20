<?php

namespace HiEvents\Services\Domain\Product\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class OrderProductPriceDTO extends BaseDTO
{
    public function __construct(
        public readonly int $quantity,
        public readonly int $price_id,
        public readonly ?float $price = null,
        public readonly array $seat_uids = [],
        public readonly ?string $band_key = null,
    ) {}
}
