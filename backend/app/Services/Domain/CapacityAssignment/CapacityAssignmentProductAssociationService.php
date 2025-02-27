<?php

namespace HiEvents\Services\Domain\CapacityAssignment;

use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class CapacityAssignmentProductAssociationService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        public readonly DatabaseManager             $databaseManager,
    )
    {
    }

    public function addCapacityToProducts(
        int    $capacityAssignmentId,
        ?array $productIds,
        bool   $removePreviousAssignments = true
    ): void
    {
        $this->databaseManager->transaction(function () use ($capacityAssignmentId, $productIds, $removePreviousAssignments) {
            $this->associateProductsWithCapacityAssignment(
                capacityAssignmentId: $capacityAssignmentId,
                productIds: $productIds,
                removePreviousAssignments: $removePreviousAssignments,
            );
        });
    }

    private function associateProductsWithCapacityAssignment(
        int    $capacityAssignmentId,
        ?array $productIds,
        bool   $removePreviousAssignments = true
    ): void
    {
        if (empty($productIds)) {
            return;
        }

        if ($removePreviousAssignments) {
            $this->productRepository->removeCapacityAssignmentFromProducts(
                capacityAssignmentId: $capacityAssignmentId,
            );
        }

        $this->productRepository->addCapacityAssignmentToProducts(
            capacityAssignmentId: $capacityAssignmentId,
            productIds: array_unique($productIds),
        );
    }
}
