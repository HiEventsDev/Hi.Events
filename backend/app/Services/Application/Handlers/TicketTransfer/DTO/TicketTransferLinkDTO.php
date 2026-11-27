<?php

namespace HiEvents\Services\Application\Handlers\TicketTransfer\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TicketTransferLinkDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly string $attendee_short_id,
        public readonly ?string $order_short_id,
        public readonly ?string $session_token,
        public readonly ?string $ip_address,
        public readonly ?string $user_agent,
        public readonly ?string $recipient_email = null,
    ) {}
}
