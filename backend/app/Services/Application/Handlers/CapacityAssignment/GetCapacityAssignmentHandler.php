<?php

namespace HiEvents\Services\Application\Handlers\CapacityAssignment;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetCapacityAssignmentHandler
{
    public function __construct(
        private readonly CapacityAssignmentRepositoryInterface $capacityAssignmentRepository,
    )
    {
    }

    public function handle(int $capacityAssignmentId, int $eventId): CapacityAssignmentDomainObject
    {
        $capacityAssignment = $this->capacityAssignmentRepository
            ->loadRelation(ProductDomainObject::class)
            ->findFirstWhere([
                'event_id' => $eventId,
                'id' => $capacityAssignmentId,
            ]);

        if ($capacityAssignment === null) {
            throw new ResourceNotFoundException('Capacity assignment not found');
        }

        return $capacityAssignment;
    }
}
