<?php

namespace HiEvents\Services\Domain\Product\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use Illuminate\Support\Collection;

class AvailableProductQuantitiesResponseDTO extends BaseDTO
{
    public function __construct(
        /** @var Collection<AvailableProductQuantitiesDTO> */
        public Collection $productQuantities,
        /** @var Collection<CapacityAssignmentDomainObject> */
        public ?Collection $capacities = null,
        public ?EventOccurrenceDomainObject $occurrence = null,
        public ?int $occurrenceReservedQuantity = null,
    ) {}

    public function getAvailableQuantityForPrice(int $productPriceId): int
    {
        return $this->productQuantities
            ->first(fn (AvailableProductQuantitiesDTO $dto) => $dto->price_id === $productPriceId)
            ?->quantity_available ?? 0;
    }

    /**
     * @param  array<int, int>  $quantityByProductId
     */
    public function firstOverflowingPool(array $quantityByProductId): ?CapacityAssignmentDomainObject
    {
        return ($this->capacities ?? collect())->first(function (CapacityAssignmentDomainObject $capacity) use ($quantityByProductId) {
            if ($capacity->isCapacityUnlimited() || $capacity->getProducts() === null) {
                return false;
            }

            $requested = $capacity->getProducts()
                ->sum(fn (ProductDomainObject $product) => $quantityByProductId[$product->getId()] ?? 0);

            return $requested > 0 && $requested > $this->remainingPoolCapacity($capacity);
        });
    }

    public function remainingPoolCapacity(CapacityAssignmentDomainObject $capacity): int
    {
        $productIds = ($capacity->getProducts() ?? collect())
            ->map(fn (ProductDomainObject $product) => $product->getId())
            ->all();

        return max(0, $capacity->getAvailableCapacity() - $this->productQuantities
            ->whereIn('product_id', $productIds)
            ->sum('quantity_reserved'));
    }

    public function remainingOccurrenceCapacity(): ?int
    {
        if ($this->occurrence?->getCapacity() === null || $this->occurrenceReservedQuantity === null) {
            return null;
        }

        return max(0, $this->occurrence->getCapacity() - $this->occurrence->getUsedCapacity() - $this->occurrenceReservedQuantity);
    }
}
