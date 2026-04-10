<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride;

use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceOccurrenceOverrideDomainObjectAbstract;
use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\DTO\UpsertPriceOverrideDTO;
use Illuminate\Database\DatabaseManager;
use Throwable;

class UpsertPriceOverrideHandler
{
    public function __construct(
        private readonly ProductPriceOccurrenceOverrideRepositoryInterface $overrideRepository,
        private readonly EventOccurrenceRepositoryInterface                $occurrenceRepository,
        private readonly ProductPriceRepositoryInterface                   $productPriceRepository,
        private readonly ProductRepositoryInterface                        $productRepository,
        private readonly DatabaseManager                                   $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpsertPriceOverrideDTO $dto): ProductPriceOccurrenceOverrideDomainObject
    {
        $occurrence = $this->occurrenceRepository->findFirstWhere([
            EventOccurrenceDomainObjectAbstract::ID => $dto->event_occurrence_id,
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $dto->event_id,
        ]);

        if (!$occurrence) {
            throw new ResourceNotFoundException(
                __('Occurrence :id not found for this event', ['id' => $dto->event_occurrence_id])
            );
        }

        $productPrice = $this->productPriceRepository->findFirst($dto->product_price_id);
        if (!$productPrice) {
            throw new ResourceNotFoundException(
                __('Product price :id not found', ['id' => $dto->product_price_id])
            );
        }

        $product = $this->productRepository->findFirstWhere([
            'id' => $productPrice->getProductId(),
            'event_id' => $dto->event_id,
        ]);

        if (!$product) {
            throw new ResourceNotFoundException(
                __('Product price :id does not belong to this event', ['id' => $dto->product_price_id])
            );
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
                    ],
                );
            }

            return $this->overrideRepository->create([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => $dto->event_occurrence_id,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRODUCT_PRICE_ID => $dto->product_price_id,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::PRICE => $dto->price,
            ]);
        });
    }
}
