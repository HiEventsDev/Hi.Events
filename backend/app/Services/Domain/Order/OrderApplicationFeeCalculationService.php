<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;

class OrderApplicationFeeCalculationService
{
    public function __construct(
        private readonly Repository $config,
    )
    {
    }

    public function calculateApplicationFee(
        AccountConfigurationDomainObject $accountConfiguration,
        float                            $orderTotal,
        string                           $currency
    ): MoneyValue
    {
        if (!$this->config->get('app.saas_mode_enabled')) {
            return MoneyValue::zero($currency);
        }

        $fixedFee = $accountConfiguration->getFixedApplicationFee();
        $percentageFee = $accountConfiguration->getPercentageApplicationFee();

        return MoneyValue::fromFloat(
            amount: $fixedFee + ($orderTotal * $percentageFee / 100),
            currency: $currency
        );
    }
}
