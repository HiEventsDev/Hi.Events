<?php

namespace HiEvents\Services\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class GetOrderPublicDTO extends BaseDTO
{
    public function __construct(
        public int    $eventId,
        public string $orderShortId,
        public bool   $includeEventInResponse = false,
    )
    {
    }
}
