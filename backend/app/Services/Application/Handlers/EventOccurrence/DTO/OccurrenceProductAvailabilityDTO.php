<?php

namespace HiEvents\Services\Application\Handlers\EventOccurrence\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class OccurrenceProductAvailabilityDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $product_id,
        public readonly int $product_price_id,
        public readonly int $quantity_sold,
        public readonly ?int $quantity_available,
    ) {}
}
