<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<CapacityAssignmentDomainObject>
 */
interface CapacityAssignmentRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;
}
