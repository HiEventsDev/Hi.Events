<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Eloquent\ProductPriceRepository;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Domain\Product\DTO\ProductPriceDTO;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductPriceUpdateService
{
    public function __construct(
        private readonly ProductPriceRepository $productPriceRepository,
        private readonly SoldAndReservedQuantitiesService $soldAndReservedQuantities,
    ) {}

    /**
     * @throws CannotDeleteEntityException
     * @throws ValidationException
     */
    public function updatePrices(
        ProductDomainObject $product,
        UpsertProductDTO $productsData,
        /** @var Collection<ProductPriceDomainObject> $existingPrices */
        Collection $existingPrices,
        EventDomainObject $event,
    ): void {
        $defaultAppliesTo = $event->isRecurring()
            ? ProductQuantityAppliesTo::defaultFor($productsData->product_type)
            : ProductQuantityAppliesTo::EVENT;

        if ($productsData->type !== ProductPriceType::TIERED) {
            $prices = new Collection([new ProductPriceDTO(
                price: $productsData->type === ProductPriceType::FREE ? 0.00 : $productsData->prices->first()->price,
                label: null,
                sale_start_date: null,
                sale_end_date: null,
                initial_quantity_available: $productsData->prices->first()->initial_quantity_available,
                id: $existingPrices->first()->getId(),
                quantity_applies_to: $productsData->prices->first()->quantity_applies_to,
            )]);
        } else {
            $prices = $productsData->prices;
        }

        $this->validateQuantityAvailable($prices, $existingPrices, $productsData->product_type, $event->isRecurring(), $defaultAppliesTo);

        $order = 1;

        foreach ($prices as $price) {
            $attributes = [
                'product_id' => $product->getId(),
                'price' => $price->price,
                'label' => $price->label,
                'sale_start_date' => $price->sale_start_date
                    ? DateHelper::convertToUTC($price->sale_start_date, $event->getTimezone())
                    : null,
                'sale_end_date' => $price->sale_end_date
                    ? DateHelper::convertToUTC($price->sale_end_date, $event->getTimezone())
                    : null,
                'initial_quantity_available' => $price->initial_quantity_available,
                'quantity_applies_to' => $event->isRecurring()
                    ? ($price->quantity_applies_to ?? $defaultAppliesTo)->name
                    : ProductQuantityAppliesTo::EVENT->name,
                'is_hidden' => $price->is_hidden,
                'order' => $order++,
            ];

            if ($price->id === null) {
                $this->productPriceRepository->create($attributes);
            } else {
                $this->productPriceRepository->updateWhere($attributes, [
                    'id' => $price->id,
                ]);
            }
        }

        $this->deletePrices($prices, $existingPrices);
    }

    /**
     * @throws ValidationException
     */
    private function validateQuantityAvailable(
        Collection $prices,
        Collection $existingPrices,
        ProductType $productType,
        bool $isRecurring,
        ProductQuantityAppliesTo $defaultAppliesTo,
    ): void {
        $perOccurrencePriceIds = $prices
            ->filter(fn (ProductPriceDTO $price) => $isRecurring
                && $price->id !== null
                && $price->initial_quantity_available !== null
                && ($price->quantity_applies_to ?? $defaultAppliesTo) === ProductQuantityAppliesTo::OCCURRENCE)
            ->map(fn (ProductPriceDTO $price) => $price->id)
            ->values()
            ->all();

        $maxSoldPerOccurrence = $perOccurrencePriceIds === []
            ? []
            : $this->soldAndReservedQuantities->getMaxSoldOnAnyOccurrenceByPrice($perOccurrencePriceIds, $productType);

        foreach ($prices as $index => $price) {
            if ($price->id === null || $price->initial_quantity_available === null) {
                continue;
            }

            /** @var ProductPriceDomainObject|null $existingPrice */
            $existingPrice = $existingPrices->first(fn (ProductPriceDomainObject $p) => $p->getId() === $price->id);

            if ($existingPrice === null) {
                continue;
            }

            $priceLabel = $existingPrice->getLabel() ?: __('Default');

            if (in_array($price->id, $perOccurrencePriceIds, true)) {
                $sold = $maxSoldPerOccurrence[$price->id] ?? 0;

                if ($price->initial_quantity_available < $sold) {
                    throw ValidationException::withMessages([
                        "prices.$index.initial_quantity_available" => __(
                            'The quantity per date for :price cannot be less than the number already sold on a single date (:sold)',
                            ['price' => $priceLabel, 'sold' => $sold]
                        ),
                    ]);
                }

                continue;
            }

            if ($price->initial_quantity_available < $existingPrice->getQuantitySold()) {
                throw ValidationException::withMessages([
                    "prices.$index.initial_quantity_available" => __(
                        'The available quantity for :price cannot be less than the number already sold (:sold)',
                        ['price' => $priceLabel, 'sold' => $existingPrice->getQuantitySold()]
                    ),
                ]);
            }
        }
    }

    /**
     * @throws CannotDeleteEntityException
     */
    private function deletePrices(Collection $prices, Collection $existingPrices): void
    {
        $pricesIds = $prices->map(fn ($price) => $price->id)->toArray();

        $existingPrices->each(function (ProductPriceDomainObject $price) use ($pricesIds) {
            if (in_array($price->getId(), $pricesIds, true)) {
                return;
            }
            if ($price->getQuantitySold() > 0) {
                throw new CannotDeleteEntityException(
                    __('Cannot delete product price with id :id because it has sales', ['id' => $price->getId()])
                );
            }
            $this->productPriceRepository->deleteById($price->getId());
        });
    }
}
