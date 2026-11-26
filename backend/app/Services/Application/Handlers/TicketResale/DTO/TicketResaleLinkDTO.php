<?php

namespace HiEvents\Services\Application\Handlers\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TicketResaleLinkDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly string $order_short_id,
        public readonly string $attendee_short_id,
        public readonly ?string $session_token,
        public readonly ?string $ip_address,
        public readonly ?string $user_agent,
    ) {}
}
