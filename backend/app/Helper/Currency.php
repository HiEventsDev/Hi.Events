<?php

namespace HiEvents\Helper;

use NumberFormatter;

class Currency
{
    public static function format(float|int $amount, string $currencyCode, string $locale = 'en_US'): string
    {
        $currencyCode = strtoupper($currencyCode);
        $formatter = new NumberFormatter($locale . '@currency=' . $currencyCode, NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($amount, $currencyCode);
    }

    public static function round(float $value, $precision = 2): float
    {
        return round(
            num: $value,
            precision: $precision,
            mode: PHP_ROUND_HALF_UP
        );
    }
}
