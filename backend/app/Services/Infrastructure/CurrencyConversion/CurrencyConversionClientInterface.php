<?php

namespace HiEvents\Services\Infrastructure\CurrencyConversion;

use Brick\Money\Currency;
use HiEvents\Values\MoneyValue;

interface CurrencyConversionClientInterface
{
    public function convert(Currency $fromCurrency, Currency $toCurrency, float $amount): MoneyValue;
}
