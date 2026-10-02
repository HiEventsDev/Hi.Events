<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\Constants;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderPlatformFeePassThroughService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Domain\Tax\DTO\TaxCalculationResponse;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Illuminate\Support\Collection;

class ProductFilterService
{
    private ?OrganizerConfigurationDomainObject $organizerConfiguration = null;

    private ?EventSettingDomainObject $eventSettings = null;

    private ?string $eventCurrency = null;

    private bool $applyPlatformFee = true;

    public function __construct(
        private readonly TaxAndFeeCalculationService $taxCalculationService,
        private readonly ProductPriceService $productPriceService,
        private readonly AvailableProductQuantitiesFetchService $fetchAvailableProductQuantitiesService,
        private readonly OrderPlatformFeePassThroughService $platformFeeService,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly ProductOccurrenceVisibilityRepositoryInterface $productOccurrenceVisibilityRepository,
        private readonly SeatedProductLookupService $seatedProductLookup,
    ) {}

    /**
     * @param  Collection<ProductCategoryDomainObject>  $productsCategories
     * @return Collection<ProductCategoryDomainObject>
     */
    public function filter(
        Collection $productsCategories,
        ?PromoCodeDomainObject $promoCode = null,
        bool $hideSoldOutProducts = true,
        ?int $eventOccurrenceId = null,
        bool $hideHiddenCategories = true,
    ): Collection {
        if ($productsCategories->isEmpty()) {
            return $productsCategories;
        }

        $products = $productsCategories
            ->flatMap(fn (ProductCategoryDomainObject $category) => $category->getProducts());

        $filteredCategories = $hideHiddenCategories
            ? $productsCategories->reject(fn (ProductCategoryDomainObject $category) => $category->getIsHidden())
            : $productsCategories;

        if ($products->isEmpty()) {
            return $filteredCategories;
        }

        $filteredProducts = $this->filterProducts($products, $promoCode, $hideSoldOutProducts, $eventOccurrenceId);

        return $filteredCategories
            ->each(fn (ProductCategoryDomainObject $category) => $category->setProducts(
                $filteredProducts->where(
                    static fn (ProductDomainObject $product) => $product->getProductCategoryId() === $category->getId()
                )
            ));
    }

    /**
     * @param  Collection<ProductDomainObject>  $products
     * @return Collection<ProductDomainObject>
     */
    public function filterProducts(
        Collection $products,
        ?PromoCodeDomainObject $promoCode = null,
        bool $hideSoldOutProducts = true,
        ?int $eventOccurrenceId = null,
        bool $allowPastOccurrence = false,
        bool $applyPlatformFee = true,
    ): Collection {
        if ($products->isEmpty()) {
            return $products;
        }

        $this->applyPlatformFee = $applyPlatformFee;
        $eventId = $products->first()->getEventId();
        $this->loadAccountConfiguration($eventId);

        $productQuantities = $this
            ->fetchAvailableProductQuantitiesService
            ->getAvailableProductQuantities(
                $eventId,
                eventOccurrenceId: $eventOccurrenceId,
                allowPastOccurrence: $allowPastOccurrence,
            );

        $filteredProducts = $products
            ->map(fn (ProductDomainObject $product) => $this->processProduct($product, $productQuantities->productQuantities, $promoCode, $eventOccurrenceId))
            ->reject(fn (ProductDomainObject $product) => $this->filterProduct($product, $promoCode, $hideSoldOutProducts))
            ->each(fn (ProductDomainObject $product) => $this->processProductPrices($product, $hideSoldOutProducts));

        if ($eventOccurrenceId !== null) {
            $filteredProducts = $this->filterByOccurrenceVisibility($filteredProducts, $eventOccurrenceId);
        }

        return $filteredProducts->values();
    }

    private function loadAccountConfiguration(int $eventId): void
    {
        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: OrganizerConfigurationDomainObject::class,
                        name: 'organizer_configuration',
                    ),
                ],
                name: 'organizer',
            ))
            ->findById($eventId);

        $this->eventSettings = $event->getEventSettings();
        $this->eventCurrency = $event->getCurrency();
        $this->organizerConfiguration = $event->getOrganizer()?->getOrganizerConfiguration();
    }

    private function isHiddenByPromoCode(ProductDomainObject $product, ?PromoCodeDomainObject $promoCode): bool
    {
        return $product->getIsHiddenWithoutPromoCode() && ! (
            $promoCode
            && $promoCode->appliesToProduct($product)
        );
    }

    private function shouldProductBeDiscounted(?PromoCodeDomainObject $promoCode, ProductDomainObject $product): bool
    {
        if ($product->isDonationType() || $product->isFreeType()) {
            return false;
        }

        if ($promoCode?->isOrderLevelDiscount()) {
            return false;
        }

        return $promoCode
            && $promoCode->isDiscountCode()
            && $promoCode->appliesToProduct($product);
    }

    /**
     * @param  Collection<AvailableProductQuantitiesDTO>  $productQuantities
     */
    private function processProduct(
        ProductDomainObject $product,
        Collection $productQuantities,
        ?PromoCodeDomainObject $promoCode = null,
        ?int $eventOccurrenceId = null,
    ): ProductDomainObject {
        $this->setBandPrices($product, $promoCode, $eventOccurrenceId);

        if ($this->shouldProductBeDiscounted($promoCode, $product)) {
            $product->getProductPrices()?->each(function (ProductPriceDomainObject $price) use ($product, $promoCode, $eventOccurrenceId) {
                $price->setPriceBeforeDiscount($price->getPrice());
                $price->setPrice($this->productPriceService->getIndividualPrice($product, $price, $promoCode, $eventOccurrenceId));
            });
        }

        if ($eventOccurrenceId !== null && ! $this->shouldProductBeDiscounted($promoCode, $product)) {
            $product->getProductPrices()?->each(function (ProductPriceDomainObject $price) use ($product, $eventOccurrenceId) {
                $overridePrice = $this->productPriceService->getIndividualPrice($product, $price, null, $eventOccurrenceId);
                if ($overridePrice !== $price->getPrice()) {
                    $price->setPrice($overridePrice);
                }
            });
        }

        $quantitiesByPriceId = $productQuantities->keyBy('price_id');

        $product->getProductPrices()?->each(function (ProductPriceDomainObject $price) use ($quantitiesByPriceId) {
            $priceQuantities = $quantitiesByPriceId->get($price->getId());
            $availableQuantity = $priceQuantities?->quantity_available;
            $availableQuantity = $availableQuantity === Constants::INFINITE ? null : $availableQuantity;
            $price->setQuantityAvailable(
                max($availableQuantity, 0)
            );
            $price->setQuantityReserved($priceQuantities?->quantity_reserved ?? 0);
        });

        $productTotal = $productQuantities
            ->filter(fn (AvailableProductQuantitiesDTO $quantity) => $quantity->product_id === $product->getId())
            ->flatMap(fn (AvailableProductQuantitiesDTO $quantity) => array_merge(
                ($quantity->capacities ?? collect())
                    ->map(fn (CapacityAssignmentDomainObject $capacity) => $capacity->getAvailableCapacity())
                    ->all(),
                $quantity->seats_available === null ? [] : [$quantity->seats_available],
            ))
            ->min();

        if ($productTotal !== null) {
            $product->setQuantityAvailable($productTotal);
        }

        return $product;
    }

    private function filterProduct(
        ProductDomainObject $product,
        ?PromoCodeDomainObject $promoCode = null,
        bool $hideSoldOutProducts = true,
    ): bool {
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
        if (! $price->isFree()) {
            $taxAndFees = $this->taxAndFeeTotals($product, $price->getPrice());

            $price
                ->setTaxTotal(Currency::round($taxAndFees->taxTotal))
                ->setFeeTotal(Currency::round($taxAndFees->feeTotal));
        }

        $price->setIsAvailable($this->getPriceAvailability($price, $product));
    }

    private function taxAndFeeTotals(ProductDomainObject $product, float $price): TaxCalculationResponse
    {
        $taxAndFees = $this->taxCalculationService->calculateTaxAndFeesForProduct($product, $price);

        $platformFee = $this->applyPlatformFee
            ? $this->calculatePlatformFee($price + $taxAndFees->feeTotal + $taxAndFees->taxTotal)
            : 0.0;

        if ($platformFee <= 0) {
            return $taxAndFees;
        }

        $this->addPlatformFeeToProduct($product);

        return new TaxCalculationResponse(
            feeTotal: $taxAndFees->feeTotal + $platformFee,
            taxTotal: $taxAndFees->taxTotal,
            rollUp: $taxAndFees->rollUp,
        );
    }

    private function setBandPrices(ProductDomainObject $product, ?PromoCodeDomainObject $promoCode, ?int $eventOccurrenceId): void
    {
        $links = $this->seatedProductLookup
            ->linksForEvent($product->getEventId())
            ->filter(fn (EventSeatMapBandProductDomainObject $link) => $link->getProductId() === $product->getId());

        if ($links->isEmpty()) {
            return;
        }

        $product->getProductPrices()?->each(fn (ProductPriceDomainObject $price) => $price->setBandPrices(
            $links->mapWithKeys(fn (EventSeatMapBandProductDomainObject $link) => [
                $link->getBandKey() => $this->priceIncludingTaxesAndFees(
                    $product,
                    $this->productPriceService->getPrice(
                        $product,
                        new OrderProductPriceDTO(quantity: 1, price_id: $price->getId()),
                        $promoCode,
                        $eventOccurrenceId,
                        bandPriceAdjustment: Currency::fromMinorUnits($link->getPriceAdjustment(), $this->eventCurrency),
                    )->price,
                ),
            ])->all(),
        ));
    }

    private function priceIncludingTaxesAndFees(ProductDomainObject $product, float $price): float
    {
        if ($price <= 0.0) {
            return 0.0;
        }

        $taxAndFees = $this->taxAndFeeTotals($product, $price);

        return Currency::round($price + Currency::round($taxAndFees->taxTotal) + Currency::round($taxAndFees->feeTotal));
    }

    private function calculatePlatformFee(float $total): float
    {
        if ($this->organizerConfiguration === null || $this->eventSettings === null) {
            return 0.0;
        }

        return $this->platformFeeService->calculatePlatformFee(
            organizerConfiguration: $this->organizerConfiguration,
            eventSettings: $this->eventSettings,
            total: $total,
            quantity: 1,
            currency: $this->eventCurrency,
        );
    }

    private function addPlatformFeeToProduct(ProductDomainObject $product): void
    {
        $existingTaxesAndFees = $product->getTaxAndFees() ?? collect();

        $hasPlatformFee = $existingTaxesAndFees->contains(
            fn (TaxAndFeesDomainObject $fee) => $fee->getId() === OrderPlatformFeePassThroughService::PLATFORM_FEE_ID
        );

        if (! $hasPlatformFee) {
            $platformFeeDomainObject = (new TaxAndFeesDomainObject)
                ->setId(OrderPlatformFeePassThroughService::PLATFORM_FEE_ID)
                ->setAccountId(0)
                ->setName(OrderPlatformFeePassThroughService::getPlatformFeeName())
                ->setType('FEE')
                ->setCalculationType('FIXED')
                ->setRate(0);

            $product->setTaxAndFees($existingTaxesAndFees->push($platformFeeDomainObject));
        }
    }

    private function filterProductPrice(
        ProductDomainObject $product,
        ProductPriceDomainObject $price,
        bool $hideSoldOutProducts = true
    ): bool {
        $hidden = false;

        if (! $product->isTieredType()) {
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

        if ($price->isLockedBehindEarlierTier() && $price->getOffSaleReason() === null) {
            $price->setOffSaleReason(__('Price is locked until earlier tiers sell out'));
        }

        return $hidden && $hideSoldOutProducts;
    }

    private function processProductPrices(ProductDomainObject $product, bool $hideSoldOutProducts = true): void
    {
        $product->markLockedTiers();

        $product->setProductPrices(
            $product->getProductPrices()
                ?->each(fn (ProductPriceDomainObject $price) => $this->processProductPrice($product, $price))
                ->reject(fn (ProductPriceDomainObject $price) => $this->filterProductPrice($product, $price, $hideSoldOutProducts))
        );
    }

    private function filterByOccurrenceVisibility(Collection $products, int $eventOccurrenceId): Collection
    {
        $visibilityRules = $this->productOccurrenceVisibilityRepository->findWhere([
            'event_occurrence_id' => $eventOccurrenceId,
        ]);

        if ($visibilityRules->isEmpty()) {
            return $products;
        }

        $visibleProductIds = $visibilityRules->map(fn ($rule) => $rule->getProductId());

        return $products->filter(
            fn (ProductDomainObject $product) => $visibleProductIds->contains($product->getId())
        );
    }

    private function getPriceAvailability(ProductPriceDomainObject $price, ProductDomainObject $product): bool
    {
        if ($product->isTieredType()) {
            return ! $price->isSoldOut()
                && ! $price->isBeforeSaleStartDate()
                && ! $price->isAfterSaleEndDate()
                && ! $price->getIsHidden()
                && ! $price->isLockedBehindEarlierTier();
        }

        return ! $product->isSoldOut()
            && ! $product->isBeforeSaleStartDate()
            && ! $product->isAfterSaleEndDate()
            && ! $product->getIsHidden();
    }
}
