<?php

namespace HiEvents\Http\DataTransferObjects;

use Illuminate\Support\Collection;
use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;

class CompleteOrderAttendeeDTO extends BaseDTO
{
    public function __construct(
        public readonly string      $first_name,
        public readonly string      $last_name,
        public readonly string      $email,
        public readonly int         $ticket_price_id,
        #[CollectionOf(OrderQuestionsDTO::class)]
        public readonly ?Collection $questions = null,
    )
    {
    }
}
