<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\ProductPriceType;
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
    )
    {
    }

    /**
     * @throws CannotDeleteEntityException
     * @throws ValidationException
     */
    public function updatePrices(
        ProductDomainObject $product,
        UpsertProductDTO    $productsData,
        /** @var Collection<ProductPriceDomainObject> $existingPrices */
        Collection          $existingPrices,
        EventDomainObject   $event,
    ): void
    {
        $this->validateQuantityAvailable($productsData->prices, $existingPrices);

        if ($productsData->type !== ProductPriceType::TIERED) {
            $prices = new Collection([new ProductPriceDTO(
                price: $productsData->type === ProductPriceType::FREE ? 0.00 : $productsData->prices->first()->price,
                label: null,
                sale_start_date: null,
                sale_end_date: null,
                initial_quantity_available: $productsData->prices->first()->initial_quantity_available,
                id: $existingPrices->first()->getId(),
            )]);
        } else {
            $prices = $productsData->prices;
        }

        $order = 1;

        foreach ($prices as $price) {
            if ($price->id === null) {
                $this->productPriceRepository->create([
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
                    'is_hidden' => $price->is_hidden,
                    'order' => $order++,
                ]);
            } else {
                $this->productPriceRepository->updateWhere([
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
                    'is_hidden' => $price->is_hidden,
                    'order' => $order++,
                ], [
                    'id' => $price->id,
                ]);
            }
        }

        $this->deletePrices($prices, $existingPrices);
    }

    /**
     * @throws ValidationException
     */
    private function validateQuantityAvailable(?Collection $prices, Collection $existingPrices): void
    {
        if ($prices === null) {
            return;
        }

        foreach ($prices as $index => $price) {
            if ($price->id === null || $price->initial_quantity_available === null) {
                continue;
            }

            /** @var ProductPriceDomainObject|null $existingPrice */
            $existingPrice = $existingPrices->first(fn(ProductPriceDomainObject $p) => $p->getId() === $price->id);

            if ($existingPrice === null) {
                continue;
            }

            if ($price->initial_quantity_available < $existingPrice->getQuantitySold()) {
                throw ValidationException::withMessages([
                    "prices.$index.initial_quantity_available" => __(
                        'The available quantity for :price cannot be less than the number already sold (:sold)',
                        [
                            'price' => $existingPrice->getLabel() ?: __('Default'),
                            'sold' => $existingPrice->getQuantitySold(),
                        ]
                    ),
                ]);
            }
        }
    }

    /**
     * @throws CannotDeleteEntityException
     */
    private function deletePrices(?Collection $prices, Collection $existingPrices): void
    {
        $pricesIds = $prices?->map(fn($price) => $price->id)->toArray();

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
