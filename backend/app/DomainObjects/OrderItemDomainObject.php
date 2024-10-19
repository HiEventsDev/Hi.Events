<?php

namespace HiEvents\DomainObjects;

use HiEvents\Helper\Currency;

class OrderItemDomainObject extends Generated\OrderItemDomainObjectAbstract
{
    private ?ProductPriceDomainObject $productPrice = null;

    public ?ProductDomainObject $product = null;

    public ?OrderDomainObject $order = null;

    public function getTotalBeforeDiscount(): float
    {
        return Currency::round($this->getPriceBeforeDiscount() * $this->getQuantity());
    }

    public function getProductPrice(): ?ProductPriceDomainObject
    {
        return $this->productPrice;
    }

    public function setProductPrice(?ProductPriceDomainObject $tier): self
    {
        $this->productPrice = $tier;

        return $this;
    }

    public function getProduct(): ?ProductDomainObject
    {
        return $this->product;
    }

    public function setProduct(?ProductDomainObject $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getOrder(): ?OrderDomainObject
    {
        return $this->order;
    }

    public function setOrder(?OrderDomainObject $order): self
    {
        $this->order = $order;

        return $this;
    }
}
