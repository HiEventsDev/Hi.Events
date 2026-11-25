<?php

namespace HiEvents\Services\Domain\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TicketResaleStateDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $can_list,
        public readonly ?string $status,
        public readonly ?string $held_until,
        public readonly bool $withdrawal_requested,
        public readonly ?string $cutoff,
    ) {}
}
