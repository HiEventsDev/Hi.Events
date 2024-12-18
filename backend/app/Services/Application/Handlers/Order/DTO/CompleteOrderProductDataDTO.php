<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;
use Illuminate\Support\Collection;

class CompleteOrderProductDataDTO extends BaseDTO
{
    public function __construct(
        public readonly int         $product_price_id,
        #[CollectionOf(OrderQuestionsDTO::class)]
        public readonly ?Collection $questions = null,

        // Only relevant for products with product type 'TICKET'
        public readonly ?string     $first_name = null,
        public readonly ?string     $last_name = null,
        public readonly ?string     $email = null,
    )
    {
    }

    public function isTicketProduct(): bool
    {
        return $this->first_name !== null
            && $this->last_name !== null
            && $this->email !== null;
    }
}
