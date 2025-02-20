<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CreatedProductDataDTO extends BaseDTO
{
    public function __construct(
        public readonly CompleteOrderProductDataDTO $productRequestData,
        public readonly ?string                      $shortId,
    )
    {
    }
}
