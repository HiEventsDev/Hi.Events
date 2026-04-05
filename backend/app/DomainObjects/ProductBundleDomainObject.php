<?php

namespace HiEvents\DomainObjects;

use Illuminate\Support\Collection;

class ProductBundleDomainObject extends Generated\ProductBundleDomainObjectAbstract
{
    private ?Collection $bundleItems = null;

    public function setBundleItems(Collection $bundleItems): self
    {
        $this->bundleItems = $bundleItems;
        return $this;
    }

    public function getBundleItems(): ?Collection
    {
        return $this->bundleItems;
    }

    public function isSoldOut(): bool
    {
        if ($this->getQuantityAvailable() === null) {
            return false;
        }

        return $this->getQuantitySold() >= $this->getQuantityAvailable();
    }
}
