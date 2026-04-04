<?php

namespace HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertPriceOverrideDTO extends BaseDataObject
{
    public function __construct(
        public readonly int   $event_id,
        public readonly int   $event_occurrence_id,
        public readonly int   $product_price_id,
        public readonly float $price,
    )
    {
    }
}
