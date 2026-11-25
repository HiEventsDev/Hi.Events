<?php

namespace HiEvents\Services\Application\Handlers\OrderAuditLog\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetTicketHistoryDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly ?int $attendee_id = null,
        public readonly ?int $order_id = null,
    ) {}
}
