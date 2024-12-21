<?php

namespace HiEvents\Services\Application\Handlers\CapacityAssignment;

use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class DeleteCapacityAssignmentHandler
{
    public function __construct(
        private readonly CapacityAssignmentRepositoryInterface $capacityAssignmentRepository,
        private readonly ProductRepositoryInterface            $productRepository,
        private readonly DatabaseManager                       $databaseManager,
    )
    {
    }

    public function handle(int $id, int $eventId): void
    {
        $this->databaseManager->transaction(function () use ($id, $eventId) {
            $this->productRepository->removeCapacityAssignmentFromProducts(
                capacityAssignmentId: $id,
            );

            $this->capacityAssignmentRepository->deleteWhere([
                'id' => $id,
                'event_id' => $eventId,
            ]);
        });
    }
}
