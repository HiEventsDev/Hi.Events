<?php

namespace HiEvents\DataTransferObjects;

class UpdateAdminAccountVatSettingDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $accountId,
        public readonly bool $vatRegistered,
        public readonly ?string $vatNumber = null,
        public readonly ?bool $vatValidated = null,
        public readonly ?string $businessName = null,
        public readonly ?string $businessAddress = null,
        public readonly ?string $vatCountryCode = null,
    )
    {
    }
}
