<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\CheckInList\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class LookupKioskTicketsPublicDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $checkInListShortId,
        public readonly string $email,
        public readonly string $lastName,
    ) {}
}
