<?php

namespace HiEvents\Services\Domain\EventOccurrence\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class OccurrenceTierAllocationDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $product_price_id,
        public readonly string $product_title,
        public readonly ?string $price_label,
        public readonly ?int $quantity,
        public readonly string $applies_to,
    ) {}
}
