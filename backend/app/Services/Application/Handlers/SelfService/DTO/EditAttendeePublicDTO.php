<?php

namespace HiEvents\Services\Application\Handlers\SelfService\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EditAttendeePublicDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $eventId,
        public readonly string $orderShortId,
        public readonly string $attendeeShortId,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $email,
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
    ) {
    }
}
