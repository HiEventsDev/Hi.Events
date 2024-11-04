<?php

namespace HiEvents\Services\Handlers\CapacityAssignment;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\Services\Domain\CapacityAssignment\UpdateCapacityAssignmentService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use HiEvents\Services\Handlers\CapacityAssignment\DTO\UpsertCapacityAssignmentDTO;

class UpdateCapacityAssignmentHandler
{
    public function __construct(
        private readonly UpdateCapacityAssignmentService $updateCapacityAssignmentService,
    )
    {
    }

    /**
     * @throws UnrecognizedProductIdException
     */
    public function handle(UpsertCapacityAssignmentDTO $data): CapacityAssignmentDomainObject
    {
        $capacityAssignment = (new CapacityAssignmentDomainObject)
            ->setId($data->id)
            ->setName($data->name)
            ->setEventId($data->event_id)
            ->setCapacity($data->capacity)
            ->setAppliesTo(CapacityAssignmentAppliesTo::PRODUCTS->name)
            ->setStatus($data->status->name);

        return $this->updateCapacityAssignmentService->updateCapacityAssignment(
            $capacityAssignment,
            $data->product_ids,
        );
    }
}
