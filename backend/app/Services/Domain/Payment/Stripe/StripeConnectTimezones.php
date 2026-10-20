<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Payment\Stripe;

use DateTimeZone;

class StripeConnectTimezones
{
    private const array SUPPORTED_COUNTRY_CODES = [
        'AE', 'AT', 'AU', 'BE', 'BG', 'CA', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GI',
        'GR', 'HK', 'HR', 'HU', 'IE', 'IT', 'JP', 'LI', 'LT', 'LU', 'LV', 'MT', 'MX', 'NL', 'NO', 'NZ', 'PL',
        'PR', 'PT', 'RO', 'SE', 'SG', 'SI', 'SK', 'US',
    ];

    /**
     * @return array<int, string>
     */
    public function unsupported(): array
    {
        return array_values(array_filter(
            DateTimeZone::listIdentifiers(),
            static function (string $timezone): bool {
                $countryCode = (new DateTimeZone($timezone))->getLocation()['country_code'] ?? '??';

                return $countryCode !== '??' && ! in_array($countryCode, self::SUPPORTED_COUNTRY_CODES, true);
            },
        ));
    }
}
