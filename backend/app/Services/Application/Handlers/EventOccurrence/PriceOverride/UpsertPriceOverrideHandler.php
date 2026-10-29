<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride;

use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceOccurrenceOverrideDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\Exceptions\InvalidOccurrenceQuantityOverrideException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\DTO\UpsertPriceOverrideDTO;
use HiEvents\Services\Domain\Product\SoldAndReservedQuantitiesService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class UpsertPriceOverrideHandler
{
    public function __construct(
        private readonly ProductPriceOccurrenceOverrideRepositoryInterface $overrideRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly ProductPriceRepositoryInterface $productPriceRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SoldAndReservedQuantitiesService $soldAndReservedQuantities,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     * @throws InvalidOccurrenceQuantityOverrideException
     */
    public function handle(UpsertPriceOverrideDTO $dto): ProductPriceOccurrenceOverrideDomainObject
    {
        $occurrence = $this->occurrenceRepository->findFirstWhere([
            EventOccurrenceDomainObjectAbstract::ID => $dto->event_occurrence_id,
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $dto->event_id,
        ]);

        if (! $occurrence) {
            throw new ResourceNotFoundException(
                __('Occurrence :id not found for this event', ['id' => $dto->event_occurrence_id])
            );
        }

        $productPrice = $this->productPriceRepository->findFirst($dto->product_price_id);
        if (! $productPrice) {
            throw new ResourceNotFoundException(
                __('Product price :id not found', ['id' => $dto->product_price_id])
            );
        }

        $product = $this->productRepository->findFirstWhere([
            'id' => $productPrice->getProductId(),
            'event_id' => $dto->event_id,
        ]);

        if (! $product) {
            throw new ResourceNotFoundException(
                __('Product price :id does not belong to this event', ['id' => $dto->product_price_id])
            );
        }

        if ($dto->quantity_available !== null) {
            $this->assertQuantityOverrideAllowed($dto, $productPrice, $product);
        }

        return $this->databaseManager->transaction(function () use ($dto) {
            $existing = $this->overrideRepository->findFirstWhere([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => $dto->event_occurrence_id,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRODUCT_PRICE_ID => $dto->product_price_id,
            ]);

            if ($existing) {
                return $this->overrideRepository->updateFromArray(
                    id: $existing->getId(),
                    attributes: [
                        ProductPriceOccurrenceOverrideDomainObjectAbstract::PRICE => $dto->price,
                        ProductPriceOccurrenceOverrideDomainObjectAbstract::QUANTITY_AVAILABLE => $dto->quantity_available,
                    ],
                );
            }

            return $this->overrideRepository->create([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => $dto->event_occurrence_id,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRODUCT_PRICE_ID => $dto->product_price_id,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRICE => $dto->price,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::QUANTITY_AVAILABLE => $dto->quantity_available,
            ]);
        });
    }

    /**
     * @throws InvalidOccurrenceQuantityOverrideException
     */
    private function assertQuantityOverrideAllowed(
        UpsertPriceOverrideDTO $dto,
        ProductPriceDomainObject $productPrice,
        ProductDomainObject $product,
    ): void {
        if ($productPrice->getQuantityAppliesTo() !== ProductQuantityAppliesTo::OCCURRENCE->name) {
            throw new InvalidOccurrenceQuantityOverrideException(
                __('Per-date quantities only apply to tiers whose quantity is per date')
            );
        }

        $sold = $this->soldAndReservedQuantities->getSoldByPriceForOccurrence(
            $dto->event_occurrence_id,
            ProductType::fromName($product->getProductType()),
        )[$dto->product_price_id] ?? 0;

        if ($dto->quantity_available < $sold) {
            throw new InvalidOccurrenceQuantityOverrideException(
                __('The quantity for :price on this date cannot be less than the number already sold (:sold)', [
                    'price' => $productPrice->getLabel() ?: __('Default'),
                    'sold' => $sold,
                ])
            );
        }
    }
}
