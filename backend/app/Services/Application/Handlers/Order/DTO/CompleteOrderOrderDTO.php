<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;
use Illuminate\Support\Collection;

class CompleteOrderOrderDTO extends BaseDTO
{
    /**
     * @param  Collection<OrderQuestionsDTO>|null  $questions
     */
    public function __construct(
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $email,
        #[CollectionOf(OrderQuestionsDTO::class)]
        public readonly ?Collection $questions,
        public readonly ?array $address = [],
        public readonly bool $opted_into_marketing = false,
    ) {}
}
