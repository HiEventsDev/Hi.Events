<?php

namespace HiEvents\Services\Application\Handlers\TicketLookup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetOrdersByLookupTokenDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $token,
    ) {
    }
}
