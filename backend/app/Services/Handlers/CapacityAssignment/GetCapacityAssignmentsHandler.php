<?php

namespace HiEvents\Services\Handlers\CapacityAssignment;

use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Services\Handlers\CapacityAssignment\DTO\GetCapacityAssignmentsDTO;
use Illuminate\Support\Collection;

class GetCapacityAssignmentsHandler
{
    public function __construct(
        private readonly CapacityAssignmentRepositoryInterface $capacityAssignmentRepository,
    )
    {
    }

    public function handle(GetCapacityAssignmentsDTO $dto): Collection
    {
        return $this->capacityAssignmentRepository
            ->loadRelation(TicketDomainObject::class)
            ->findWhere([
                'event_id' => $dto->eventId,
            ]);
    }
}
