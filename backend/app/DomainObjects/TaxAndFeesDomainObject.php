<?php

namespace HiEvents\DomainObjects;

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
}
