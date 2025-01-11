<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Generated\CapacityAssignmentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
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
    )
    {
    }

    public function increaseQuantitySold(int $priceId, int $adjustment = 1): void
    {
        $this->databaseManager->transaction(function () use ($priceId, $adjustment) {
            $capacityAssignments = $this->getCapacityAssignments($priceId);

            $capacityAssignments->each(function (CapacityAssignmentDomainObjectAbstract $capacityAssignment) use ($adjustment) {
                $this->increaseCapacityAssignmentUsedCapacity($capacityAssignment->getId(), $adjustment);
            });

            $this->productPriceRepository->updateWhere([
                'quantity_sold' => DB::raw('quantity_sold + ' . $adjustment),
            ], [
                'id' => $priceId,
            ]);
        });
    }

    public function decreaseQuantitySold(int $priceId, int $adjustment = 1): void
    {
        $this->databaseManager->transaction(function () use ($priceId, $adjustment) {
            $capacityAssignments = $this->getCapacityAssignments($priceId);

            $capacityAssignments->each(function (CapacityAssignmentDomainObjectAbstract $capacityAssignment) use ($adjustment) {
                $this->decreaseCapacityAssignmentUsedCapacity($capacityAssignment->getId(), $adjustment);
            });

            $this->productPriceRepository->updateWhere([
                'quantity_sold' => DB::raw('quantity_sold - ' . $adjustment),
            ], [
                'id' => $priceId,
            ]);
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
            $this->increaseQuantitySold($orderItem->getProductPriceId(), $orderItem->getQuantity());
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
            CapacityAssignmentDomainObjectAbstract::USED_CAPACITY => DB::raw(CapacityAssignmentDomainObjectAbstract::USED_CAPACITY . ' - ' . $adjustment),
        ], [
            'id' => $capacityAssignmentId,
        ]);
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
