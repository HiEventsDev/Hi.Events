<?php

namespace HiEvents\Services\Application\Handlers\Waitlist\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CreateWaitlistEntryDTO extends BaseDataObject
{
    public function __construct(
        public int     $event_id,
        public int     $product_price_id,
        public string  $email,
        public string  $first_name,
        public ?string $last_name = null,
        public string  $locale = 'en',
    )
    {
    }
}
