<?php

declare(strict_types=1);

namespace HiEvents\DataTransferObjects;

class AddressDTO extends BaseDataObject
{
    public function __construct(
        public readonly ?string $venue_name = null,
        public readonly ?string $address_line_1 = null,
        public readonly ?string $address_line_2 = null,
        public readonly ?string $city = null,
        public readonly ?string $state_or_region = null,
        public readonly ?string $zip_or_postal_code = null,
        public readonly ?string $country = null,
    ) {}
}
