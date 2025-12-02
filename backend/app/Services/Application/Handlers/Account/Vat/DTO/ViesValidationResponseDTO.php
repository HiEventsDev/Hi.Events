<?php

namespace HiEvents\Services\Application\Handlers\Account\Vat\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ViesValidationResponseDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $businessName = null,
        public readonly ?string $businessAddress = null,
        public readonly string $countryCode = '',
        public readonly string $vatNumber = '',
    ) {
    }
}
