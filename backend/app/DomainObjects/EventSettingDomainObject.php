<?php

namespace HiEvents\DomainObjects;

use HiEvents\DataTransferObjects\AddressDTO;

class EventSettingDomainObject extends Generated\EventSettingDomainObjectAbstract
{
    /**
     * @return string
     * @todo This should not be here.
     */
    public function getGetEmailFooterHtml(): string
    {
        if ($this->getEmailFooterMessage() === null) {
            return '';
        }

        return <<<HTML
<div style="color: #888; margin-top: 30px; margin-bottom: 30px; font-size: .9em;">
    {$this->getEmailFooterMessage()}
</div>
HTML;
    }

    public function getAddressString(): string
    {
        $locationDetails = $this->getLocationDetails();

        if (is_null($locationDetails)) {
            return '';
        }

        $addressParts = [
            $locationDetails['venue_name'] ?? null,
            $locationDetails['address_line_1'] ?? null,
            $locationDetails['address_line_2'] ?? null,
            $locationDetails['city'] ?? null,
            $locationDetails['state_or_region'] ?? null,
            $locationDetails['zip_or_postal_code'] ?? null,
            $locationDetails['country'] ?? null
        ];

        $filteredAddressParts = array_filter($addressParts, static fn($part) => !is_null($part) && $part !== '');

        return implode(', ', $filteredAddressParts);
    }

    public function getAddress(): AddressDTO
    {
        return new AddressDTO(
            venue_name: $this->getLocationDetails()['venue_name'] ?? null,
            address_line_1: $this->getLocationDetails()['address_line_1'] ?? null,
            address_line_2: $this->getLocationDetails()['address_line_2'] ?? null,
            city: $this->getLocationDetails()['city'] ?? null,
            state_or_region: $this->getLocationDetails()['state_or_region'] ?? null,
            zip_or_postal_code: $this->getLocationDetails()['zip_or_postal_code'] ?? null,
            country: $this->getLocationDetails()['country'] ?? null,
        );
    }
}
