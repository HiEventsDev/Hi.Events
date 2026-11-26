<?php

namespace HiEvents\Services\Domain\TicketTransfer\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\TicketTransferDomainObject;

class TicketTransferStateDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $can_start,
        public readonly ?string $blocked_reason,
        public readonly ?TicketTransferDomainObject $pending,
    ) {}
}
