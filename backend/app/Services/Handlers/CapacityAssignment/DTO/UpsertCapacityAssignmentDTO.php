<?php

namespace HiEvents\Services\Handlers\CapacityAssignment\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\DomainObjects\Status\CapacityAssignmentStatus;

class UpsertCapacityAssignmentDTO extends BaseDTO
{
    public function __construct(
        public string                      $name,
        public int                         $event_id,
        public CapacityAssignmentAppliesTo $applies_to,
        public CapacityAssignmentStatus    $status,

        public ?int                        $capacity,
        public ?array                      $ticket_ids = null,
        public ?int                        $id = null,
    )
    {
    }
}
