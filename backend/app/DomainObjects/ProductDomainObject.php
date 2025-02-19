<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\Constants;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use Illuminate\Support\Collection;
use LogicException;

class ProductDomainObject extends Generated\ProductDomainObjectAbstract implements IsSortable
{
    private ?Collection $taxAndFees = null;

    private ?Collection $prices = null;

    private ?string $offSaleReason = null;

    private ?int $quantityAvailable = null;

    public static function getDefaultSort(): string
    {
        return self::ORDER;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::ORDER => [
                    'asc' => __('Homepage order'),
                ],
                self::CREATED_AT => [
                    'asc' => __('Oldest first'),
                    'desc' => __('Newest first'),
                ],
                self::TITLE => [
                    'asc' => __('Title A-Z'),
                    'desc' => __('Title Z-A'),
                ],
                self::SALE_START_DATE => [
                    'asc' => __('Sale start date closest'),
                    'desc' => __('Sale start date furthest'),
                ],
                self::SALE_END_DATE => [
                    'asc' => __('Sale end date closest'),
                    'desc' => __('Sale end date furthest'),
                ],
            ]
        );
    }

    public function setTaxAndFees(Collection $taxes): ProductDomainObject
    {
        $this->taxAndFees = $taxes;
        return $this;
    }

    public function getTaxRates(): ?Collection
    {
        return $this->getTaxAndFees()?->filter(fn(TaxAndFeesDomainObject $taxAndFee) => $taxAndFee->isTax());
    }

    public function getTaxAndFees(): ?Collection
    {
        return $this->taxAndFees;
    }

    public function getFees(): ?Collection
    {
        return $this->getTaxAndFees()?->filter(fn(TaxAndFeesDomainObject $taxAndFee) => $taxAndFee->isFee());
    }

    public function isSoldOut(): bool
    {
        if (!$this->getProductPrices() || $this->getProductPrices()->isEmpty()) {
            return true;
        }

        return $this->getProductPrices()->every(fn(ProductPriceDomainObject $price) => $price->isSoldOut());
    }

    public function getQuantityAvailable(): int
    {
        $availableCount = $this->getProductPrices()->sum(fn(ProductPriceDomainObject $price) => $price->getQuantityAvailable());

        if ($this->quantityAvailable !== null) {
            return min($availableCount, $this->quantityAvailable);
        }

        if (!$this->getProductPrices() || $this->getProductPrices()->isEmpty()) {
            return 0;
        }

        // This is to address a case where prices have an unlimited quantity available and the user has
        // enabled show_quantity_remaining.
        if ($this->getShowQuantityRemaining()
            && $this->getProductPrices()->first(fn(ProductPriceDomainObject $price) => $price->getQuantityAvailable() === null)) {
            return Constants::INFINITE;
        }

        return $availableCount;
    }

    public function setQuantityAvailable(int $quantityAvailable): ProductDomainObject
    {
        $this->quantityAvailable = $quantityAvailable;

        return $this;
    }

    public function isBeforeSaleStartDate(): bool
    {
        return (!is_null($this->getSaleStartDate())
            && (new Carbon($this->getSaleStartDate()))->isFuture()
        );
    }

    public function isAfterSaleEndDate(): bool
    {
        return (!is_null($this->getSaleEndDate())
            && (new Carbon($this->getSaleEndDate()))->isPast()
        );
    }

    public function isAvailable(): bool
    {
        // If all prices are hidden, it's not available
        if ($this->getType() === ProductPriceType::TIERED->name && $this->getProductPrices()?->isEmpty()) {
            return false;
        }

        return !$this->isSoldOut()
            && !$this->isBeforeSaleStartDate()
            && !$this->isAfterSaleEndDate()
            && !$this->getIsHidden();
    }

    /**
     * @return Collection<ProductPriceDomainObject>|null
     */
    public function getProductPrices(): ?Collection
    {
        return $this->prices;
    }

    public function setProductPrices(?Collection $prices): self
    {
        $this->prices = $prices;

        return $this;
    }

    /**
     * All product types except TIERED have a single price, so we can just return the first price.
     *
     * @return float|null
     */
    public function getPrice(): ?float
    {
        if ($this->getType() === ProductPriceType::TIERED->name) {
            throw new LogicException('You cannot get a single price for a tiered product. Use getPrices() instead.');
        }

        return $this->getProductPrices()?->first()?->getPrice();
    }

    public function getPriceById(int $priceId): ?ProductPriceDomainObject
    {
        return $this->getProductPrices()?->first(fn(ProductPriceDomainObject $price) => $price->getId() === $priceId);
    }

    public function isTieredType(): bool
    {
        return $this->getType() === ProductPriceType::TIERED->name;
    }

    public function isDonationType(): bool
    {
        return $this->getType() === ProductPriceType::DONATION->name;
    }

    public function isPaidType(): bool
    {
        return $this->getType() === ProductPriceType::PAID->name;
    }

    public function isFreeType(): bool
    {
        return $this->getType() === ProductPriceType::FREE->name;
    }

    public function getInitialQuantityAvailable(): ?int
    {
        if ($this->getType() === ProductPriceType::TIERED->name) {
            return $this->getProductPrices()?->sum(fn(ProductPriceDomainObject $price) => $price->getInitialQuantityAvailable());
        }

        return $this->getProductPrices()?->first()?->getInitialQuantityAvailable();
    }

    public function getQuantitySold(): int
    {
        return $this->getProductPrices()?->sum(fn(ProductPriceDomainObject $price) => $price->getQuantitySold()) ?? 0;
    }

    public function setOffSaleReason(?string $offSaleReason): ProductDomainObject
    {
        $this->offSaleReason = $offSaleReason;

        return $this;
    }

    public function getOffSaleReason(): ?string
    {
        return $this->offSaleReason;
    }
}
