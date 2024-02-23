<?php

namespace TicketKitten\Http\DataTransferObjects;

use Illuminate\Support\Collection;
use TicketKitten\DataTransferObjects\Attributes\CollectionOf;
use TicketKitten\DataTransferObjects\BaseDTO;

class CompleteOrderOrderDTO extends BaseDTO
{
    /**
     * @param string $first_name
     * @param string $last_name
     * @param string $email
     * @param Collection<OrderQuestionsDTO>|null $questions
     * @param array|null $address
     */
    public function __construct(
        public readonly string      $first_name,
        public readonly string      $last_name,
        public readonly string      $email,
        #[CollectionOf(OrderQuestionsDTO::class)]
        public readonly ?Collection $questions,
        public readonly ?array      $address = [],
    )
    {
    }
}
