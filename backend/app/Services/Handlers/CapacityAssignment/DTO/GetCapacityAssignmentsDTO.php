<?php

namespace HiEvents\Services\Handlers\CapacityAssignment\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class GetCapacityAssignmentsDTO extends BaseDTO
{
    public function __construct(
        public int $eventId,
    )
    {
    }
}
