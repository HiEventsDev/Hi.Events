<?php

namespace HiEvents\Services\Domain\CheckInList;

use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class CheckInListProductAssociationService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        public readonly DatabaseManager $databaseManager,
    ) {}

    public function addCheckInListToProducts(
        int $checkInListId,
        ?array $productIds,
        bool $removePreviousAssignments = true
    ): void {
        $this->databaseManager->transaction(function () use ($checkInListId, $productIds, $removePreviousAssignments) {
            $this->associateProductsWithCheckInList(
                checkInListId: $checkInListId,
                productIds: $productIds,
                removePreviousAssignments: $removePreviousAssignments,
            );
        });
    }

    private function associateProductsWithCheckInList(
        int $checkInListId,
        ?array $productIds,
        bool $removePreviousAssignments = true
    ): void {
        if ($removePreviousAssignments) {
            $this->productRepository->removeCheckInListFromProducts(
                checkInListId: $checkInListId,
            );
        }

        if (empty($productIds)) {
            return;
        }

        $this->productRepository->addCheckInListToProducts(
            checkInListId: $checkInListId,
            productIds: array_unique($productIds),
        );
    }
}
