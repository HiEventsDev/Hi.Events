<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class GetOrderInvoiceDTO extends BaseDTO
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $eventId,
    )
    {
    }
}
