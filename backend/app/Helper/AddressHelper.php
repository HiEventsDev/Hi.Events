<?php

namespace HiEvents\Helper;

class AddressHelper
{
    public static function formatAddress(?array $address): string
    {
        if (is_null($address)) {
            return '';
        }

        $addressParts = [
            $address['venue_name'] ?? null,
            $address['address_line_1'] ?? null,
            $address['address_line_2'] ?? null,
            $address['city'] ?? null,
            $address['state_or_region'] ?? null,
            $address['zip_or_postal_code'] ?? null,
            $address['country'] ?? null
        ];

        $filteredAddressParts = array_filter($addressParts, static fn($part) => !is_null($part) && $part !== '');

        return implode(', ', $filteredAddressParts);
    }
}

