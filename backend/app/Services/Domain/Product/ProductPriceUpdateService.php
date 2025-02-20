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

class ProductPriceUpdateService
{
    public function __construct(
        private readonly ProductPriceRepository $productPriceRepository,
    )
    {
    }

    /**
     * @throws CannotDeleteEntityException
     */
    public function updatePrices(
        ProductDomainObject $product,
        UpsertProductDTO    $productsData,
        /** @var Collection<ProductPriceDomainObject> $existingPrices */
        Collection          $existingPrices,
        EventDomainObject   $event,
    ): void
    {
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
