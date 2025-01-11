<?php

namespace HiEvents\DomainObjects;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\Helper\AddressHelper;

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
        return AddressHelper::formatAddress($this->getLocationDetails());
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
