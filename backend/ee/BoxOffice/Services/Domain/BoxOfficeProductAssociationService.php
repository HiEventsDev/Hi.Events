<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class BoxOfficeProductAssociationService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function addBoxOfficeToProducts(
        int $boxOfficeId,
        ?array $productIds,
        bool $removePreviousAssignments = true,
    ): void {
        $this->databaseManager->transaction(function () use ($boxOfficeId, $productIds, $removePreviousAssignments) {
            if ($removePreviousAssignments) {
                $this->productRepository->removeBoxOfficeFromProducts($boxOfficeId);
            }

            if (empty($productIds)) {
                return;
            }

            $this->productRepository->addBoxOfficeToProducts(
                boxOfficeId: $boxOfficeId,
                productIds: array_unique($productIds),
            );
        });
    }
}
