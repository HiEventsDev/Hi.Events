<?php

namespace HiEvents\Services\Handlers\CapacityAssignment;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\Services\Domain\CapacityAssignment\CreateCapacityAssignmentService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use HiEvents\Services\Handlers\CapacityAssignment\DTO\UpsertCapacityAssignmentDTO;

class CreateCapacityAssignmentHandler
{
    public function __construct(
        private readonly CreateCapacityAssignmentService $createCapacityAssignmentService
    )
    {
    }

    /**
     * @throws UnrecognizedProductIdException
     */
    public function handle(UpsertCapacityAssignmentDTO $data): CapacityAssignmentDomainObject
    {
        $capacityAssignment = (new CapacityAssignmentDomainObject)
            ->setName($data->name)
            ->setEventId($data->event_id)
            ->setCapacity($data->capacity)
            ->setAppliesTo(CapacityAssignmentAppliesTo::PRODUCTS->name)
            ->setStatus($data->status->name);

        return $this->createCapacityAssignmentService->createCapacityAssignment(
            $capacityAssignment,
            $data->product_ids,
        );
    }
}
