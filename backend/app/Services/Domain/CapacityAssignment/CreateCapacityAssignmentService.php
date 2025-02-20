<?php

namespace HiEvents\Services\Domain\CapacityAssignment;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\DomainObjects\Generated\CapacityAssignmentDomainObjectAbstract;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Database\DatabaseManager;

class CreateCapacityAssignmentService
{
    public function __construct(
        private readonly DatabaseManager                            $databaseManager,
        private readonly CapacityAssignmentRepositoryInterface      $capacityAssignmentRepository,
        private readonly EventProductValidationService              $eventProductValidationService,
        private readonly CapacityAssignmentProductAssociationService $capacityAssignmentProductAssociationService,
        private readonly ProductPriceRepositoryInterface            $productPriceRepository,
    )
    {
    }

    /**
     * @throws UnrecognizedProductIdException
     */
    public function createCapacityAssignment(
        CapacityAssignmentDomainObject $capacityAssignment,
        array                          $productIds,
    ): CapacityAssignmentDomainObject
    {
        $this->eventProductValidationService->validateProductIds($productIds, $capacityAssignment->getEventId());

        return $this->persistAssignmentAndAssociateProducts($capacityAssignment, $productIds);
    }

    private function persistAssignmentAndAssociateProducts(
        CapacityAssignmentDomainObject $capacityAssignment,
        ?array                         $productIds,
    ): CapacityAssignmentDomainObject
    {
        return $this->databaseManager->transaction(function () use ($capacityAssignment, $productIds) {
            /** @var CapacityAssignmentDomainObject $capacityAssignment */
            $capacityAssignment = $this->capacityAssignmentRepository->create([
                CapacityAssignmentDomainObjectAbstract::NAME => $capacityAssignment->getName(),
                CapacityAssignmentDomainObjectAbstract::EVENT_ID => $capacityAssignment->getEventId(),
                CapacityAssignmentDomainObjectAbstract::CAPACITY => $capacityAssignment->getCapacity(),
                CapacityAssignmentDomainObjectAbstract::APPLIES_TO => $capacityAssignment->getAppliesTo(),
                CapacityAssignmentDomainObjectAbstract::STATUS => $capacityAssignment->getStatus(),
                CapacityAssignmentDomainObjectAbstract::USED_CAPACITY => $this->getUsedCapacity($productIds),
            ]);

            if ($capacityAssignment->getAppliesTo() === CapacityAssignmentAppliesTo::PRODUCTS->name) {
                $this->capacityAssignmentProductAssociationService->addCapacityToProducts(
                    capacityAssignmentId: $capacityAssignment->getId(),
                    productIds: $productIds,
                    removePreviousAssignments: false,
                );
            }

            return $capacityAssignment;
        });
    }

    private function getUsedCapacity(array $productIds): int
    {
        $productPrices = $this->productPriceRepository->findWhereIn('product_id', $productIds);

        return $productPrices->sum(fn(ProductPriceDomainObject $productPrice) => $productPrice->getQuantitySold());
    }
}
