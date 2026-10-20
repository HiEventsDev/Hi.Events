<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\Constants;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\ProductFilterService;
use Illuminate\Support\Collection;

class BoxOfficeProductCatalogueService
{
    public function __construct(
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductFilterService $productFilterService,
        private readonly AvailableProductQuantitiesFetchService $availableProductQuantitiesFetchService,
    ) {}

    /**
     * @return Collection<ProductDomainObject>
     */
    public function getSellableProducts(BoxOfficeDomainObject $boxOffice, ?int $occurrenceId): Collection
    {
        $products = $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->loadRelation(TaxAndFeesDomainObject::class)
            ->findWhere(['event_id' => $boxOffice->getEventId()])
            ->sortBy(fn (ProductDomainObject $product) => $product->getOrder())
            ->values();

        $scopedProductIds = $this->boxOfficeRepository
            ->loadRelation(ProductDomainObject::class)
            ->findById($boxOffice->getId())
            ->getProducts()
            ?->map(fn (ProductDomainObject $product) => $product->getId())
            ->all() ?? [];

        $sellable = $this->productFilterService
            ->filterProducts(
                products: $products,
                promoCode: null,
                hideSoldOutProducts: false,
                eventOccurrenceId: $occurrenceId,
                allowPastOccurrence: true,
                applyPlatformFee: false,
            )
            ->filter(fn (ProductDomainObject $product) => $this->isSellable($product, $scopedProductIds))
            ->values();

        $quantities = $this->availableProductQuantitiesFetchService->getAvailableProductQuantities(
            eventId: $boxOffice->getEventId(),
            ignoreCache: true,
            eventOccurrenceId: $occurrenceId,
            allowPastOccurrence: true,
        )->productQuantities;

        $sellable->each(function (ProductDomainObject $product) use ($quantities) {
            $product->getProductPrices()?->each(function (ProductPriceDomainObject $price) use ($quantities) {
                /** @var AvailableProductQuantitiesDTO|null $quantity */
                $quantity = $quantities->firstWhere('price_id', $price->getId());
                $available = $quantity?->quantity_available ?? 0;
                $price->setQuantityAvailable($available === Constants::INFINITE ? null : max($available, 0));
                $price->setIsAvailable($available === Constants::INFINITE || $available > 0);
            });
        });

        return $sellable;
    }

    private function isSellable(ProductDomainObject $product, array $scopedProductIds): bool
    {
        if (! in_array($product->getProductType(), [ProductType::TICKET->name, ProductType::GENERAL->name], true)) {
            return false;
        }

        if ($product->getType() === ProductPriceType::DONATION->name) {
            return false;
        }

        return $scopedProductIds === [] || in_array($product->getId(), $scopedProductIds, true);
    }
}
