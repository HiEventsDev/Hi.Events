<?php

namespace HiEvents\Services\Application\Handlers\CapacityAssignment;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Services\Application\Handlers\CapacityAssignment\DTO\GetCapacityAssignmentsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetCapacityAssignmentsHandler
{
    public function __construct(
        private readonly CapacityAssignmentRepositoryInterface $capacityAssignmentRepository,
    )
    {
    }

    public function handle(GetCapacityAssignmentsDTO $dto): LengthAwarePaginator
    {
        return $this->capacityAssignmentRepository
            ->loadRelation(ProductDomainObject::class)
            ->findByEventId(
                eventId: $dto->eventId,
                params: $dto->queryParams,
            );
    }
}
