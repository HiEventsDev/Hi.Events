<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\Helper\Currency;
use LogicException;

class ProductPriceDomainObject extends Generated\ProductPriceDomainObjectAbstract
{
    public ?ProductDomainObject $product = null;

    private ?float $priceBeforeDiscount = null;

    private ?float $taxTotal = null;

    private ?float $feeTotal = null;

    private ?bool $isAvailable = null;

    private ?string $offSaleReason = null;

    private int $quantityReserved = 0;

    private bool $isLockedBehindEarlierTier = false;

    public function getPriceBeforeDiscount(): ?float
    {
        return $this->priceBeforeDiscount;
    }

    public function setPriceBeforeDiscount(?float $originalPrice): ProductPriceDomainObject
    {
        $this->priceBeforeDiscount = $originalPrice;

        return $this;
    }

    public function getTaxTotal(): ?float
    {
        return $this->taxTotal ?? 0.00;
    }

    public function setTaxTotal(?float $taxTotal): self
    {
        $this->taxTotal = $taxTotal;

        return $this;
    }

    public function setFeeTotal(?float $feeTotal): self
    {
        $this->feeTotal = $feeTotal;

        return $this;
    }

    public function getFeeTotal(): ?float
    {
        return $this->feeTotal ?? null;
    }

    public function getPriceIncludingTaxAndServiceFee(): float
    {
        return Currency::round($this->getPrice() + $this->getTaxTotal() + $this->getFeeTotal());
    }

    public function isBeforeSaleStartDate(): bool
    {
        return ! is_null($this->getSaleStartDate())
            && (new Carbon($this->getSaleStartDate()))->isFuture();
    }

    public function isAfterSaleEndDate(): bool
    {
        return ! is_null($this->getSaleEndDate())
            && (new Carbon($this->getSaleEndDate()))->isPast();
    }

    public function isSoldOut(): bool
    {
        if ($this->getQuantityAvailable() < 0) {
            throw new LogicException('Quantity available cannot be less than 0');
        }

        if ($this->getQuantityAvailable() !== null) {
            return $this->getQuantityAvailable() <= 0;
        }

        if ($this->getInitialQuantityAvailable() === null || $this->isQuantityPerOccurrence()) {
            return false;
        }

        return $this->getQuantitySold() >= $this->getInitialQuantityAvailable();
    }

    public function isQuantityPerOccurrence(): bool
    {
        return $this->getQuantityAppliesTo() === ProductQuantityAppliesTo::OCCURRENCE->name;
    }

    public function isExhausted(): bool
    {
        if ($this->isAfterSaleEndDate()) {
            return true;
        }

        if ($this->isQuantityPerOccurrence()) {
            return $this->getQuantityAvailable() !== null && $this->getQuantityAvailable() <= 0;
        }

        return $this->getInitialQuantityAvailable() !== null
            && $this->getQuantitySold() + $this->quantityReserved >= $this->getInitialQuantityAvailable();
    }

    public function setQuantityReserved(int $quantityReserved): self
    {
        $this->quantityReserved = $quantityReserved;

        return $this;
    }

    public function isLockedBehindEarlierTier(): bool
    {
        return $this->isLockedBehindEarlierTier;
    }

    public function setIsLockedBehindEarlierTier(bool $isLocked): self
    {
        $this->isLockedBehindEarlierTier = $isLocked;

        return $this;
    }

    public function isAvailable(): ?bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(?bool $isAvailable): ProductPriceDomainObject
    {
        $this->isAvailable = $isAvailable;

        return $this;
    }

    public function setOffSaleReason(?string $offSaleReason): ProductPriceDomainObject
    {
        $this->offSaleReason = $offSaleReason;

        return $this;
    }

    public function getOffSaleReason(): ?string
    {
        return $this->offSaleReason;
    }

    public function isFree(): bool
    {
        return $this->getPrice() === 0.00;
    }

    public function setProduct(?ProductDomainObject $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getProduct(): ?ProductDomainObject
    {
        return $this->product;
    }
}
