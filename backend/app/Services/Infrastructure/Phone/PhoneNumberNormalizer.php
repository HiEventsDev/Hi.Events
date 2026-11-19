<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Phone;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberNormalizer
{
    private const MOBILE_CAPABLE_TYPES = [
        PhoneNumberType::MOBILE,
        PhoneNumberType::FIXED_LINE_OR_MOBILE,
    ];

    private const MIN_SEARCH_DIGITS = 6;

    public static function searchDigits(string $query): ?string
    {
        if (preg_match('/^[\d\s+().-]+$/', $query) !== 1) {
            return null;
        }

        $digits = ltrim(preg_replace('/\D/', '', $query), '0');

        return strlen($digits) >= self::MIN_SEARCH_DIGITS ? $digits : null;
    }

    public function toMobileE164(?string $value): ?string
    {
        $value = trim((string) $value);

        if (! str_starts_with($value, '+')) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($value);
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumber($number) || ! in_array($util->getNumberType($number), self::MOBILE_CAPABLE_TYPES, true)) {
            return null;
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }

    public function countryOf(?string $e164): ?string
    {
        if ($e164 === null || $e164 === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            return $util->getRegionCodeForNumber($util->parse($e164));
        } catch (NumberParseException) {
            return null;
        }
    }
}
