<?php

namespace HiEvents\Services\Application\Handlers\Account\Vat\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertAccountVatSettingDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $accountId,
        public readonly bool $vatRegistered,
        public readonly ?string $vatNumber = null,
    ) {
    }
}
