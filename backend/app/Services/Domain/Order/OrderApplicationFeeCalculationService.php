<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
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
        float                            $orderTotal
    ): float
    {
        if (!$this->config->get('app.saas_mode_enabled')) {
            return 0;
        }

        $fixedFee = $accountConfiguration->getFixedApplicationFee();
        $percentageFee = $accountConfiguration->getPercentageApplicationFee();

        return ($fixedFee) + ($orderTotal * ($percentageFee / 100));
    }
}
