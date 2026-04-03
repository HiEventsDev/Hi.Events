<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EditAdminAttendeeDTO extends BaseDataObject
{
    public function __construct(
        public readonly int     $attendeeId,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $email = null,
        public readonly ?string $notes = null,
    )
    {
    }
}
