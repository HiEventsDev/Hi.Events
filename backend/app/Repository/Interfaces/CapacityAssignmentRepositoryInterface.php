<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<CapacityAssignmentDomainObject>
 */
interface CapacityAssignmentRepositoryInterface extends RepositoryInterface
{
    public function findCapacityAssignments(int $eventId): LengthAwarePaginator;
}
