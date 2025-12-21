<?php

namespace HiEvents\Services\Domain\Order;

use Brick\Money\Currency;
use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Services\Domain\Order\DTO\ApplicationFeeValuesDTO;
use HiEvents\Services\Domain\Order\Vat\VatRateDeterminationService;
use HiEvents\Services\Infrastructure\CurrencyConversion\CurrencyConversionClientInterface;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;

class OrderApplicationFeeCalculationService
{
    private const BASE_CURRENCY = 'USD';

    public function __construct(
        private readonly Repository                        $config,
        private readonly CurrencyConversionClientInterface $currencyConversionClient,
        private readonly VatRateDeterminationService       $vatRateDeterminationService,
    )
    {
    }

    public function calculateApplicationFee(
        AccountConfigurationDomainObject $accountConfiguration,
        OrderDomainObject                $order,
        ?AccountVatSettingDomainObject   $vatSettings = null
    ): ?ApplicationFeeValuesDTO
    {
        $currency = $order->getCurrency();
        $quantityPurchased = $this->getChargeableQuantityPurchased($order);

        if (!$this->config->get('app.saas_mode_enabled')) {
            return null;
        }

        $fixedFee = $this->getConvertedFixedFee($accountConfiguration, $currency);
        $percentageFee = $accountConfiguration->getPercentageApplicationFee();

        $netApplicationFee = MoneyValue::fromFloat(
            amount: ($fixedFee->toFloat() * $quantityPurchased) + ($order->getTotalGross() * $percentageFee / 100),
            currency: $currency
        );

        if (!$vatSettings) {
            return new ApplicationFeeValuesDTO(
                grossApplicationFee: $netApplicationFee,
                netApplicationFee: $netApplicationFee,
            );
        }

        return $this->calculateFeeWithVat(
            vatSettings: $vatSettings,
            netApplicationFee: $netApplicationFee,
            currency: $currency,
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

    /**
     * Calculate application fee with VAT added on top.
     *
     * Note: This uses a VAT-exclusive approach where VAT is calculated on top of the
     * net application fee and added to reach the gross amount charged to the customer.
     * For example, with a 12% application fee on a £5.00 order and 20% VAT:
     * - Net application fee: £0.60 (12% of order)
     * - VAT: £0.12 (20% of £0.60)
     * - Gross charged: £0.72 (£0.60 + £0.12)
     */
    private function calculateFeeWithVat(
        AccountVatSettingDomainObject $vatSettings,
        MoneyValue                    $netApplicationFee,
        string                        $currency,
    ): ApplicationFeeValuesDTO
    {
        $vatRate = $this->vatRateDeterminationService->determineVatRatePercentage($vatSettings);

        if ($vatRate <= 0) {
            return new ApplicationFeeValuesDTO(
                grossApplicationFee: $netApplicationFee,
                netApplicationFee: $netApplicationFee,
                applicationFeeVatRate: $vatRate,
            );
        }

        $vatAmount = MoneyValue::fromFloat(
            amount: $netApplicationFee->toFloat() * ($vatRate),
            currency: $currency
        );

        $grossApplicationFee = MoneyValue::fromFloat(
            amount: $netApplicationFee->toFloat() + $vatAmount->toFloat(),
            currency: $currency
        );

        return new ApplicationFeeValuesDTO(
            grossApplicationFee: $grossApplicationFee,
            netApplicationFee: $netApplicationFee,
            applicationFeeVatRate: $vatRate,
            applicationFeeVatAmount: $vatAmount,
        );
    }
}
