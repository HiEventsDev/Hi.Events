<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\TaxAndFeeApplicationType;
use HiEvents\DomainObjects\Enums\TaxType;

class TaxAndFeesDomainObject extends Generated\TaxAndFeesDomainObjectAbstract
{
    public function isTax(): bool
    {
        return $this->getType() === TaxType::TAX->name;
    }

    public function isFee(): bool
    {
        return $this->getType() === TaxType::FEE->name;
    }

    public function isPerOrder(): bool
    {
        return $this->getApplicationType() === TaxAndFeeApplicationType::PER_ORDER->name;
    }

    public function isPerProduct(): bool
    {
        return $this->getApplicationType() === TaxAndFeeApplicationType::PER_PRODUCT->name;
    }
}
