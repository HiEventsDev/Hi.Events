<?php

namespace HiEvents\Services\Application\Handlers\Waitlist\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class OfferWaitlistEntryDTO extends BaseDataObject
{
    public function __construct(
        public int  $event_id,
        public ?int $product_price_id = null,
        public ?int $entry_id = null,
        public int  $quantity = 1,
    )
    {
    }
}
