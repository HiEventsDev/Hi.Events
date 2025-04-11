<?php

namespace HiEvents\Services\Domain\Order;

use Brick\Money\Currency;
use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\Services\Infrastructure\CurrencyConversion\CurrencyConversionClientInterface;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;

class OrderApplicationFeeCalculationService
{
    private const BASE_CURRENCY = 'USD';

    public function __construct(
        private readonly Repository                        $config,
        private readonly CurrencyConversionClientInterface $currencyConversionClient
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

        $fixedFee = $this->getConvertedFixedFee($accountConfiguration, $currency);
        $percentageFee = $accountConfiguration->getPercentageApplicationFee();

        return MoneyValue::fromFloat(
            amount: $fixedFee->toFloat() + ($orderTotal * $percentageFee / 100),
            currency: $currency
        );
    }

    private function getConvertedFixedFee(
        AccountConfigurationDomainObject $accountConfiguration,
        string                           $currency
    ): MoneyValue
    {
        if ($currency === self::BASE_CURRENCY) {
            return MoneyValue::fromFloat($accountConfiguration->getFixedApplicationFee(), $currency);
        }

        return $this->currencyConversionClient->convert(
            fromCurrency: Currency::of(self::BASE_CURRENCY),
            toCurrency: Currency::of($currency),
            amount: $accountConfiguration->getFixedApplicationFee()
        );
    }
}
