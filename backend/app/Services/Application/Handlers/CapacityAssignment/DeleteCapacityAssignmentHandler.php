<?php

namespace HiEvents\Services\Application\Handlers\CapacityAssignment;

use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Models\CapacityAssignment;
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
        $capacityAssignment = $this->capacityAssignmentRepository->findById($id);

        $productIds = CapacityAssignment::find($id)?->products()->pluck('products.id')->toArray() ?? [];

        $this->databaseManager->transaction(function () use ($id, $eventId) {
            $this->productRepository->removeCapacityAssignmentFromProducts(
                capacityAssignmentId: $id,
            );

            $this->capacityAssignmentRepository->deleteWhere([
                'id' => $id,
                'event_id' => $eventId,
            ]);
        });

        if ($capacityAssignment->getCapacity() !== null) {
            foreach ($productIds as $productId) {
                event(new CapacityChangedEvent(
                    eventId: $eventId,
                    direction: CapacityChangeDirection::INCREASED,
                    productId: $productId,
                ));
            }
        }
    }
}
