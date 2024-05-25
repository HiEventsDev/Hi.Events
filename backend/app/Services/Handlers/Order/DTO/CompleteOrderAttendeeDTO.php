<?php

namespace HiEvents\Services\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;
use Illuminate\Support\Collection;

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
