<?php

namespace HiEvents\Http\DataTransferObjects;

use Illuminate\Support\Collection;
use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;

class CompleteOrderDTO extends BaseDTO
{
    /**
     * @param CompleteOrderOrderDTO $order
     * @param Collection<CompleteOrderAttendeeDTO> $attendees
     */
    public function __construct(
        public CompleteOrderOrderDTO $order,
        #[CollectionOf(CompleteOrderAttendeeDTO::class)]
        public Collection            $attendees
    )
    {
    }
}
