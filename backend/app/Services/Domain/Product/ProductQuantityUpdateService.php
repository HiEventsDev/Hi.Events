<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Generated\CapacityAssignmentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductQuantityUpdateService
{
    public function __construct(
        private readonly ProductPriceRepositoryInterface       $productPriceRepository,
        private readonly ProductRepositoryInterface            $productRepository,
        private readonly CapacityAssignmentRepositoryInterface $capacityAssignmentRepository,
        private readonly DatabaseManager                       $databaseManager,
        private readonly EventOccurrenceRepositoryInterface    $occurrenceRepository,
    )
    {
    }

    public function increaseQuantitySold(int $priceId, int $adjustment = 1, ?int $eventOccurrenceId = null): void
    {
        $this->databaseManager->transaction(function () use ($priceId, $adjustment, $eventOccurrenceId) {
            $capacityAssignments = $this->getCapacityAssignments($priceId);

            $capacityAssignments->each(function (CapacityAssignmentDomainObjectAbstract $capacityAssignment) use ($adjustment) {
                $this->increaseCapacityAssignmentUsedCapacity($capacityAssignment->getId(), $adjustment);
            });

            $this->productPriceRepository->updateWhere([
                'quantity_sold' => DB::raw('quantity_sold + ' . $adjustment),
            ], [
                'id' => $priceId,
            ]);

            if ($eventOccurrenceId !== null) {
                $this->increaseOccurrenceUsedCapacity($eventOccurrenceId, $adjustment);
            }
        });
    }

    public function decreaseQuantitySold(int $priceId, int $adjustment = 1, ?int $eventOccurrenceId = null): void
    {
        $this->databaseManager->transaction(function () use ($priceId, $adjustment, $eventOccurrenceId) {
            $capacityAssignments = $this->getCapacityAssignments($priceId);

            $capacityAssignments->each(function (CapacityAssignmentDomainObjectAbstract $capacityAssignment) use ($adjustment) {
                $this->decreaseCapacityAssignmentUsedCapacity($capacityAssignment->getId(), $adjustment);
            });

            $this->productPriceRepository->updateWhere([
                'quantity_sold' => DB::raw('GREATEST(0, quantity_sold - ' . $adjustment . ')'),
            ], [
                'id' => $priceId,
            ]);

            if ($eventOccurrenceId !== null) {
                $this->decreaseOccurrenceUsedCapacity($eventOccurrenceId, $adjustment);
            }
        });
    }

    /**
     * @todo - this should be in a separate service. This service shouldn't know about orders
     */
    public function updateQuantitiesFromOrder(OrderDomainObject $order): void
    {
        $this->databaseManager->transaction(function () use ($order) {
            if ($order->getOrderItems() === null) {
                throw new InvalidArgumentException(__('Order has no order items'));
            }

            $this->updateProductQuantities($order);
        });
    }

    /**
     * @param OrderDomainObject $order
     * @return void
     */
    private function updateProductQuantities(OrderDomainObject $order): void
    {
        /** @var OrderItemDomainObject $orderItem */
        foreach ($order->getOrderItems() as $orderItem) {
            $this->increaseQuantitySold(
                $orderItem->getProductPriceId(),
                $orderItem->getQuantity(),
                $orderItem->getEventOccurrenceId(),
            );
        }
    }

    private function increaseCapacityAssignmentUsedCapacity(int $capacityAssignmentId, int $adjustment = 1): void
    {
        $this->capacityAssignmentRepository->updateWhere([
            CapacityAssignmentDomainObjectAbstract::USED_CAPACITY => DB::raw(CapacityAssignmentDomainObjectAbstract::USED_CAPACITY . ' + ' . $adjustment),
        ], [
            'id' => $capacityAssignmentId,
        ]);
    }

    private function decreaseCapacityAssignmentUsedCapacity(int $capacityAssignmentId, int $adjustment = 1): void
    {
        $this->capacityAssignmentRepository->updateWhere([
            CapacityAssignmentDomainObjectAbstract::USED_CAPACITY => DB::raw('GREATEST(0, ' . CapacityAssignmentDomainObjectAbstract::USED_CAPACITY . ' - ' . $adjustment . ')'),
        ], [
            'id' => $capacityAssignmentId,
        ]);
    }

    private function increaseOccurrenceUsedCapacity(int $occurrenceId, int $adjustment): void
    {
        $this->occurrenceRepository->updateWhere([
            'used_capacity' => DB::raw('used_capacity + ' . $adjustment),
        ], [
            'id' => $occurrenceId,
        ]);

        $occurrence = $this->occurrenceRepository->findById($occurrenceId);

        if (
            $occurrence->getStatus() === EventOccurrenceStatus::ACTIVE->name
            && $occurrence->getCapacity() !== null
            && $occurrence->getUsedCapacity() >= $occurrence->getCapacity()
        ) {
            $this->occurrenceRepository->updateWhere([
                'status' => EventOccurrenceStatus::SOLD_OUT->name,
            ], [
                'id' => $occurrenceId,
            ]);
        }
    }

    private function decreaseOccurrenceUsedCapacity(int $occurrenceId, int $adjustment): void
    {
        $this->occurrenceRepository->updateWhere([
            'used_capacity' => DB::raw('GREATEST(0, used_capacity - ' . $adjustment . ')'),
        ], [
            'id' => $occurrenceId,
        ]);

        $occurrence = $this->occurrenceRepository->findById($occurrenceId);

        if (
            $occurrence->getStatus() === EventOccurrenceStatus::SOLD_OUT->name
            && $occurrence->getCapacity() !== null
            && $occurrence->getUsedCapacity() < $occurrence->getCapacity()
        ) {
            $this->occurrenceRepository->updateWhere([
                'status' => EventOccurrenceStatus::ACTIVE->name,
            ], [
                'id' => $occurrenceId,
            ]);
        }
    }

    /**
     * @param int $priceId
     * @return Collection<CapacityAssignmentDomainObject>
     */
    private function getCapacityAssignments(int $priceId): Collection
    {
        $price = $this->productPriceRepository->findFirstWhere([
            'id' => $priceId,
        ]);

        return $this->productRepository->getCapacityAssignmentsByProductId($price->getProductId());
    }
}
