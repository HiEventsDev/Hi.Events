<?php

namespace HiEvents\Services\Application\Handlers\SelfService\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ResendEmailPublicDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $eventId,
        public readonly string $orderShortId,
        public readonly ?string $attendeeShortId,
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
    ) {
    }
}
