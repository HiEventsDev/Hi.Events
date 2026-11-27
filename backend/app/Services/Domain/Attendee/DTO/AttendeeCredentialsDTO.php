<?php

namespace HiEvents\Services\Domain\Attendee\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class AttendeeCredentialsDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $short_id,
        public readonly string $public_id,
    ) {}
}
