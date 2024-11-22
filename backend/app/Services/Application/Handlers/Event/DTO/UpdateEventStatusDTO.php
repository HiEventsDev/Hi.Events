<?php

namespace HiEvents\Services\Application\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class UpdateEventStatusDTO extends BaseDTO
{
    public function __construct(
        public string $status,
        public int $eventId,
        public int $accountId,
    )
    {
    }
}
