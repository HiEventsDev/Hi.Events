<?php

namespace HiEvents\Services\Application\Handlers\SelfService\Verification\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SelfServiceLinkDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly ?string $order_short_id = null,
        public readonly ?string $attendee_short_id = null,
        public readonly ?string $code = null,
    ) {}
}
