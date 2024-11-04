<?php

namespace HiEvents\Services\Domain\CapacityAssignment;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\DomainObjects\Generated\CapacityAssignmentDomainObjectAbstract;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Database\DatabaseManager;

class UpdateCapacityAssignmentService
{
    public function __construct(
        private readonly DatabaseManager                            $databaseManager,
        private readonly CapacityAssignmentRepositoryInterface      $capacityAssignmentRepository,
        private readonly EventProductValidationService              $eventProductValidationService,
        private readonly CapacityAssignmentProductAssociationService $capacityAssignmentProductAssociationService,
    )
    {
    }

    /**
     * @throws UnrecognizedProductIdException
     */
    public function updateCapacityAssignment(
        CapacityAssignmentDomainObject $capacityAssignment,
        ?array                         $productIds = null,
    ): CapacityAssignmentDomainObject
    {
        if ($productIds !== null) {
            $this->eventProductValidationService->validateProductIds($productIds, $capacityAssignment->getEventId());
        }

        return $this->updateAssignmentAndAssociateProducts($capacityAssignment, $productIds);
    }

    private function updateAssignmentAndAssociateProducts(
        CapacityAssignmentDomainObject $capacityAssignment,
        ?array                         $productIds
    ): CapacityAssignmentDomainObject
    {
        return $this->databaseManager->transaction(function () use ($capacityAssignment, $productIds) {
            /** @var CapacityAssignmentDomainObject $capacityAssignment */
            $this->capacityAssignmentRepository->updateWhere(
                attributes: [
                    CapacityAssignmentDomainObjectAbstract::NAME => $capacityAssignment->getName(),
                    CapacityAssignmentDomainObjectAbstract::EVENT_ID => $capacityAssignment->getEventId(),
                    CapacityAssignmentDomainObjectAbstract::CAPACITY => $capacityAssignment->getCapacity(),
                    CapacityAssignmentDomainObjectAbstract::APPLIES_TO => $capacityAssignment->getAppliesTo(),
                    CapacityAssignmentDomainObjectAbstract::STATUS => $capacityAssignment->getStatus(),
                ],
                where: [
                    CapacityAssignmentDomainObjectAbstract::ID => $capacityAssignment->getId(),
                    CapacityAssignmentDomainObjectAbstract::EVENT_ID => $capacityAssignment->getEventId(),
                ]
            );

            if ($capacityAssignment->getAppliesTo() === CapacityAssignmentAppliesTo::PRODUCTS->name) {
                $this->capacityAssignmentProductAssociationService->addCapacityToProducts(
                    capacityAssignmentId: $capacityAssignment->getId(),
                    productIds: $productIds,
                );
            }

            return $this->capacityAssignmentRepository->findFirstWhere([
                CapacityAssignmentDomainObjectAbstract::ID => $capacityAssignment->getId(),
                CapacityAssignmentDomainObjectAbstract::EVENT_ID => $capacityAssignment->getEventId(),
            ]);
        });
    }
}
