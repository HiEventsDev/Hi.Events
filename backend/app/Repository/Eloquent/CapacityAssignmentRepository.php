<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\Models\CapacityAssignment;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;

class CapacityAssignmentRepository extends BaseRepository implements CapacityAssignmentRepositoryInterface
{
    protected function getModel(): string
    {
        return CapacityAssignment::class;
    }

    public function getDomainObject(): string
    {
        return CapacityAssignmentDomainObject::class;
    }
}
