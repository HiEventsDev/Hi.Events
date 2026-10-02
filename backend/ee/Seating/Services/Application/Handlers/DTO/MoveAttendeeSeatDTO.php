<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class MoveAttendeeSeatDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly int $attendee_id,
        public readonly string $seat_uid,
        public readonly string $ip_address,
        public readonly ?string $user_agent,
    ) {}
}
