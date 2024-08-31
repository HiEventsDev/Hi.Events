<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use HiEvents\Helper\Currency;
use LogicException;

class TicketPriceDomainObject extends Generated\TicketPriceDomainObjectAbstract
{
    private ?float $priceBeforeDiscount = null;

    private ?float $taxTotal = null;

    private ?float $feeTotal = null;

    private ?bool $isAvailable = null;

    private ?string $offSaleReason = null;

    public function getPriceBeforeDiscount(): ?float
    {
        return $this->priceBeforeDiscount;
    }

    public function setPriceBeforeDiscount(?float $originalPrice): TicketPriceDomainObject
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

    public function isSoldOut(): bool
    {
        // todo this is temporary to see why/when this happens
        if ($this->getQuantityAvailable() < 0) {
            throw new LogicException('Quantity available cannot be less than 0');
        }

        if ($this->getQuantityAvailable() !== null && $this->getQuantityAvailable() <= 0) {
            return true;
        }

       if ($this->getInitialQuantityAvailable() === null) {
            return false;
        }

        return $this->getQuantitySold() >= $this->getInitialQuantityAvailable();
    }

    public function isAvailable(): ?bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(?bool $isAvailable): TicketPriceDomainObject
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }

    public function setOffSaleReason(?string $offSaleReason): TicketPriceDomainObject
    {
        $this->offSaleReason = $offSaleReason;

        return $this;
    }

    public function getOffSaleReason(): ?string
    {
        return $this->offSaleReason;
    }
}
