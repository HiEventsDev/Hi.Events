<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\Constants;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Illuminate\Support\Collection;

class ProductFilterService
{
    public function __construct(
        private readonly TaxAndFeeCalculationService            $taxCalculationService,
        private readonly ProductPriceService                    $productPriceService,
        private readonly AvailableProductQuantitiesFetchService $fetchAvailableProductQuantitiesService,
    )
    {
    }

    /**
     * @param Collection<ProductCategoryDomainObject> $productsCategories
     * @param PromoCodeDomainObject|null $promoCode
     * @param bool $hideSoldOutProducts
     * @return Collection<ProductCategoryDomainObject>
     */
    public function filter(
        Collection             $productsCategories,
        ?PromoCodeDomainObject $promoCode = null,
        bool                   $hideSoldOutProducts = true,
    ): Collection
    {
        if ($productsCategories->isEmpty()) {
            return $productsCategories;
        }

        $products = $productsCategories
            ->flatMap(fn(ProductCategoryDomainObject $category) => $category->getProducts());

        if ($products->isEmpty()) {
            return $productsCategories
                ->reject(fn(ProductCategoryDomainObject $category) => $category->getIsHidden());
        }

        $productQuantities = $this
            ->fetchAvailableProductQuantitiesService
            ->getAvailableProductQuantities($products->first()->getEventId());

        $filteredProducts = $products
            ->map(fn(ProductDomainObject $product) => $this->processProduct($product, $productQuantities->productQuantities, $promoCode))
            ->reject(fn(ProductDomainObject $product) => $this->filterProduct($product, $promoCode, $hideSoldOutProducts))
            ->each(fn(ProductDomainObject $product) => $this->processProductPrices($product, $hideSoldOutProducts));

        return $productsCategories
            ->reject(fn(ProductCategoryDomainObject $category) => $category->getIsHidden())
            ->each(fn(ProductCategoryDomainObject $category) => $category->setProducts(
                $filteredProducts->where(
                    static fn(ProductDomainObject $product) => $product->getProductCategoryId() === $category->getId()
                )
            ));
    }

    private function isHiddenByPromoCode(ProductDomainObject $product, ?PromoCodeDomainObject $promoCode): bool
    {
        return $product->getIsHiddenWithoutPromoCode() && !(
                $promoCode
                && $promoCode->appliesToProduct($product)
            );
    }

    private function shouldProductBeDiscounted(?PromoCodeDomainObject $promoCode, ProductDomainObject $product): bool
    {
        if ($product->isDonationType() || $product->isFreeType()) {
            return false;
        }

        return $promoCode
            && $promoCode->isDiscountCode()
            && $promoCode->appliesToProduct($product);
    }

    /**
     * @param PromoCodeDomainObject|null $promoCode
     * @param ProductDomainObject $product
     * @param Collection<AvailableProductQuantitiesDTO> $productQuantities
     * @return ProductDomainObject
     */
    private function processProduct(
        ProductDomainObject    $product,
        Collection             $productQuantities,
        ?PromoCodeDomainObject $promoCode = null,
    ): ProductDomainObject
    {
        if ($this->shouldProductBeDiscounted($promoCode, $product)) {
            $product->getProductPrices()?->each(function (ProductPriceDomainObject $price) use ($product, $promoCode) {
                $price->setPriceBeforeDiscount($price->getPrice());
                $price->setPrice($this->productPriceService->getIndividualPrice($product, $price, $promoCode));
            });
        }

        $product->getProductPrices()?->map(function (ProductPriceDomainObject $price) use ($productQuantities) {
            $availableQuantity = $productQuantities->where('price_id', $price->getId())->first()?->quantity_available;
            $availableQuantity = $availableQuantity === Constants::INFINITE ? null : $availableQuantity;
            $price->setQuantityAvailable(
                max($availableQuantity, 0)
            );
        });

        // If there is a capacity assigned to the product, we set the capacity to capacity available qty, or the sum of all
        // product prices qty, whichever is lower
        $productQuantities->each(function (AvailableProductQuantitiesDTO $quantity) use ($product) {
            if ($quantity->capacities !== null && $quantity->capacities->isNotEmpty() && $quantity->product_id === $product->getId()) {
                $product->setQuantityAvailable(
                    $quantity->capacities->min(fn(CapacityAssignmentDomainObject $capacity) => $capacity->getAvailableCapacity())
                );
            }
        });

        return $product;
    }

    private function filterProduct(
        ProductDomainObject    $product,
        ?PromoCodeDomainObject $promoCode = null,
        bool                   $hideSoldOutProducts = true,
    ): bool
    {
        $hidden = false;

        if ($this->isHiddenByPromoCode($product, $promoCode)) {
            $product->setOffSaleReason(__('Product is hidden without promo code'));
            $hidden = true;
        }

        if ($product->isSoldOut() && $product->getHideWhenSoldOut()) {
            $product->setOffSaleReason(__('Product is sold out'));
            $hidden = true;
        }

        if ($product->isBeforeSaleStartDate() && $product->getHideBeforeSaleStartDate()) {
            $product->setOffSaleReason(__('Product is before sale start date'));
            $hidden = true;
        }

        if ($product->isAfterSaleEndDate() && $product->getHideAfterSaleEndDate()) {
            $product->setOffSaleReason(__('Product is after sale end date'));
            $hidden = true;
        }

        if ($product->getIsHidden()) {
            $product->setOffSaleReason(__('Product is hidden'));
            $hidden = true;
        }

        return $hidden && $hideSoldOutProducts;
    }

    private function processProductPrice(ProductDomainObject $product, ProductPriceDomainObject $price): void
    {
        // If the product is free of charge, we don't charge service fees or taxes
        if (!$price->isFree()) {
            $taxAndFees = $this->taxCalculationService
                ->calculateTaxAndFeesForProductPrice($product, $price);

            $price
                ->setTaxTotal(Currency::round($taxAndFees->taxTotal))
                ->setFeeTotal(Currency::round($taxAndFees->feeTotal));
        }

        $price->setIsAvailable($this->getPriceAvailability($price, $product));
    }

    private function filterProductPrice(
        ProductDomainObject      $product,
        ProductPriceDomainObject $price,
        bool                     $hideSoldOutProducts = true
    ): bool
    {
        $hidden = false;

        if (!$product->isTieredType()) {
            return false;
        }

        if ($price->isBeforeSaleStartDate() && $product->getHideBeforeSaleStartDate()) {
            $price->setOffSaleReason(__('Price is before sale start date'));
            $hidden = true;
        }

        if ($price->isAfterSaleEndDate() && $product->getHideAfterSaleEndDate()) {
            $price->setOffSaleReason(__('Price is after sale end date'));
            $hidden = true;
        }

        if ($price->isSoldOut() && $product->getHideWhenSoldOut()) {
            $price->setOffSaleReason(__('Price is sold out'));
            $hidden = true;
        }

        if ($price->getIsHidden()) {
            $price->setOffSaleReason(__('Price is hidden'));
            $hidden = true;
        }

        return $hidden && $hideSoldOutProducts;
    }

    private function processProductPrices(ProductDomainObject $product, bool $hideSoldOutProducts = true): void
    {
        $product->setProductPrices(
            $product->getProductPrices()
                ?->each(fn(ProductPriceDomainObject $price) => $this->processProductPrice($product, $price))
                ->reject(fn(ProductPriceDomainObject $price) => $this->filterProductPrice($product, $price, $hideSoldOutProducts))
        );
    }

    /**
     * For non-tiered products, we can inherit the availability of the product.
     *
     * @param ProductPriceDomainObject $price
     * @param ProductDomainObject $product
     * @return bool
     */
    private function getPriceAvailability(ProductPriceDomainObject $price, ProductDomainObject $product): bool
    {
        if ($product->isTieredType()) {
            return !$price->isSoldOut()
                && !$price->isBeforeSaleStartDate()
                && !$price->isAfterSaleEndDate()
                && !$price->getIsHidden();
        }

        return !$product->isSoldOut()
            && !$product->isBeforeSaleStartDate()
            && !$product->isAfterSaleEndDate()
            && !$product->getIsHidden();
    }
}
