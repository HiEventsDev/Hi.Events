<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Vat\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertOrganizerVatSettingDTO extends BaseDataObject
{
    public function __construct(
        public readonly int     $organizerId,
        public readonly int     $accountId,
        public readonly bool    $vatRegistered,
        public readonly ?string $vatNumber = null,
    )
    {
    }
}
