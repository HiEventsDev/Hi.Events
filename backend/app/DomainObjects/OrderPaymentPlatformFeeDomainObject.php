<?php

namespace HiEvents\DomainObjects;

use HiEvents\Values\MoneyValue;

class OrderPaymentPlatformFeeDomainObject extends Generated\OrderPaymentPlatformFeeDomainObjectAbstract
{
    public function getTotalFeeMinorUnit(): int
    {
        $rollup = $this->getFeeRollup();
        $rollup = is_string($rollup) ? json_decode($rollup, true) : $rollup;

        if (is_array($rollup) && isset($rollup['total_fee'])) {
            return (int) $rollup['total_fee'];
        }

        return MoneyValue::fromFloat($this->getPaymentPlatformFeeAmount() + $this->getApplicationFeeGrossAmount(), $this->getCurrency())->toMinorUnit();
    }

    public function getCardFeeMinorUnit(): int
    {
        return max(0, $this->getTotalFeeMinorUnit() - MoneyValue::fromFloat($this->getApplicationFeeGrossAmount(), $this->getCurrency())->toMinorUnit());
    }
}
