<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;
use Illuminate\Support\Collection;

class CompleteOrderDTO extends BaseDTO
{
    /**
     * @param CompleteOrderOrderDTO $order
     * @param Collection<CompleteOrderProductDataDTO> $products
     * @param int $event_id
     */
    public function __construct(
        public CompleteOrderOrderDTO $order,
        #[CollectionOf(CompleteOrderProductDataDTO::class)]
        public Collection            $products,
        public int                   $event_id,
    )
    {
    }
}
