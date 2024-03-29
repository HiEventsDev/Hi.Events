<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use LogicException;
use HiEvents\Helper\Currency;

class TicketPriceDomainObject extends Generated\TicketPriceDomainObjectAbstract
{
    private ?float $priceBeforeDiscount = null;

    private ?float $taxTotal = null;

    private ?float $feeTotal = null;

    private ?int $quantityAvailable = null;

    private ?bool $isAvailable = null;

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

    public function setQuantityAvailable(?int $quantityAvailable): TicketPriceDomainObject
    {
        $this->quantityAvailable = $quantityAvailable;
        return $this;
    }

    public function getQuantityAvailable(): ?int
    {
        return $this->quantityAvailable;
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
}
