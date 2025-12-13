<?php

namespace HiEvents\Services\Domain\Order;

use Brick\Money\Currency as BrickCurrency;
use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Services\Infrastructure\CurrencyConversion\CurrencyConversionClientInterface;
use Illuminate\Config\Repository;

class OrderPlatformFeePassThroughService
{
    private const BASE_CURRENCY = 'USD';

    public const PLATFORM_FEE_ID = 0;

    public static function getPlatformFeeName(): string
    {
        return __('Platform Fee');
    }

    public function __construct(
        private readonly Repository                        $config,
        private readonly CurrencyConversionClientInterface $currencyConversionClient,
    )
    {
    }

    public function isEnabled(EventSettingDomainObject $eventSettings): bool
    {
        if (!$this->config->get('app.saas_mode_enabled')) {
            return false;
        }

        return $eventSettings->getPassPlatformFeeToBuyer();
    }

    /**
     * Calculate platform fee that exactly covers Stripe's application fee.
     *
     * Formula: P = (fixed + total * r) / (1 - r)
     * Where r = percentage rate, P = platform fee
     *
     * This ensures: Stripe fee on (total + P) = P
     */
    public function calculatePlatformFee(
        AccountConfigurationDomainObject $accountConfiguration,
        EventSettingDomainObject         $eventSettings,
        float                            $total,
        int                              $quantity,
        string                           $currency,
    ): float
    {
        if (!$this->isEnabled($eventSettings) || $total <= 0) {
            return 0.0;
        }

        $fixedFee = $this->getConvertedFixedFee($accountConfiguration, $currency);
        $percentageRate = $accountConfiguration->getPercentageApplicationFee() / 100;

        if ($percentageRate >= 1) {
            return Currency::round(($fixedFee * $quantity) + ($total * $percentageRate));
        }

        $totalFixedFee = $fixedFee * $quantity;
        $platformFee = ($totalFixedFee + ($total * $percentageRate)) / (1 - $percentageRate);

        return Currency::round($platformFee);
    }

    private function getConvertedFixedFee(
        AccountConfigurationDomainObject $accountConfiguration,
        string                           $currency
    ): float
    {
        $baseFee = $accountConfiguration->getFixedApplicationFee();

        if ($currency === self::BASE_CURRENCY) {
            return $baseFee;
        }

        return $this->currencyConversionClient->convert(
            fromCurrency: BrickCurrency::of(self::BASE_CURRENCY),
            toCurrency: BrickCurrency::of($currency),
            amount: $baseFee
        )->toFloat();
    }
}
