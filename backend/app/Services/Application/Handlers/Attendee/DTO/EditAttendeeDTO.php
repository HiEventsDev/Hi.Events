<?php

namespace HiEvents\Services\Application\Handlers\Attendee\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class EditAttendeeDTO extends BaseDTO
{
    public function __construct(
        public string  $first_name,
        public string  $last_name,
        public string  $email,
        public int     $product_id,
        public int     $product_price_id,
        public int     $event_id,
        public int     $attendee_id,
        public ?string $notes = null,
    )
    {
    }
}
