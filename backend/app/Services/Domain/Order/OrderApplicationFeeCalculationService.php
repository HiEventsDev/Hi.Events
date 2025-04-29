<?php

namespace HiEvents\Services\Domain\Order;

use Brick\Money\Currency;
use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
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
        OrderDomainObject                $order,
    ): MoneyValue
    {
        $currency = $order->getCurrency();
        $quantityPurchased = $this->getChargeableQuantityPurchased($order);

        if (!$this->config->get('app.saas_mode_enabled')) {
            return MoneyValue::zero($currency);
        }

        $fixedFee = $this->getConvertedFixedFee($accountConfiguration, $currency);
        $percentageFee = $accountConfiguration->getPercentageApplicationFee();

        return MoneyValue::fromFloat(
            amount: ($fixedFee->toFloat() * $quantityPurchased) + ($order->getTotalGross() * $percentageFee / 100),
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

    private function getChargeableQuantityPurchased(OrderDomainObject $order): int
    {
        $quantityPurchased = 0;
        foreach ($order->getOrderItems() as $item) {
            if ($item->getPrice() > 0) {
                $quantityPurchased += $item->getQuantity();
            }
        }

        return $quantityPurchased;
    }
}
