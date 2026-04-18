<?php

namespace HiEvents\Services\Application\Handlers\Contact\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class LookupContactByEmailPublicDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $eventId,
        public readonly string $email,
    ) {}
}
