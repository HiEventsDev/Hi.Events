<?php

namespace HiEvents\Services\Handlers\CapacityAssignment;

use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Services\Handlers\CapacityAssignment\DTO\GetCapacityAssignmentsDTO;
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
            ->loadRelation(TicketDomainObject::class)
            ->findByEventId(
                eventId: $dto->eventId,
                params: $dto->queryParams,
            );
    }
}
