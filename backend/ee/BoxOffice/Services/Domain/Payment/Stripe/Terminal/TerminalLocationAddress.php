<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\Stripe\TerminalLocationAddressMissingException;

class TerminalLocationAddress
{
    private const COMPARED_FIELDS = ['line1', 'line2', 'city', 'state', 'postal_code', 'country'];

    private const REGION_AND_POSTAL_CODE_COUNTRIES = ['US', 'CA'];

    /**
     * @throws TerminalLocationAddressMissingException
     */
    public function fromOrganizer(OrganizerDomainObject $organizer): array
    {
        $address = AddressDTO::from($organizer->getLocationRecord()?->getStructuredAddress() ?? []);

        if (! $address->address_line_1 || ! $address->city || ! $address->country) {
            throw new TerminalLocationAddressMissingException(
                __('Add a street address, city and country to your organizer profile before taking card payments')
            );
        }

        if ($this->requiresRegionAndPostalCode($address->country) && (! $address->state_or_region || ! $address->zip_or_postal_code)) {
            throw new TerminalLocationAddressMissingException(
                __('Add a state or province and a postal code to your organizer profile before taking card payments')
            );
        }

        return array_filter([
            'line1' => $address->address_line_1,
            'line2' => $address->address_line_2,
            'city' => $address->city,
            'state' => $address->state_or_region,
            'postal_code' => $address->zip_or_postal_code,
            'country' => $address->country,
        ]);
    }

    public function matches(?array $stripeAddress, array $desired): bool
    {
        foreach (self::COMPARED_FIELDS as $field) {
            if ($this->normalise($stripeAddress[$field] ?? null) !== $this->normalise($desired[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public function sameCountry(?array $stripeAddress, array $desired): bool
    {
        return $this->normalise($stripeAddress['country'] ?? null) === $this->normalise($desired['country'] ?? null);
    }

    private function requiresRegionAndPostalCode(string $country): bool
    {
        return in_array(strtoupper($country), self::REGION_AND_POSTAL_CODE_COUNTRIES, true);
    }

    private function normalise(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
