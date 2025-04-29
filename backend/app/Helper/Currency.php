<?php

namespace HiEvents\Helper;

use NumberFormatter;

class Currency
{
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF',
        'CLP',
        'DJF',
        'GNF',
        'JPY',
        'KMF',
        'KRW',
        'MGA',
        'PYG',
        'RWF',
        'UGX',
        'VND',
        'VUV',
        'XAF',
        'XOF',
        'XPF'
    ];

    public static function isZeroDecimalCurrency(string $currencyCode): bool
    {
        return in_array(strtoupper($currencyCode), self::ZERO_DECIMAL_CURRENCIES, true);
    }

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
