<?php

namespace HiEvents\Services\Application\Handlers\TicketTransfer\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Services\Domain\TicketTransfer\DTO\TicketTransferContextDTO;
use Illuminate\Support\Collection;

class TicketTransferClaimDTO extends BaseDataObject
{
    public function __construct(
        public readonly TicketTransferDomainObject $transfer,
        public readonly TicketTransferContextDTO $context,
        public readonly bool $can_accept,
        public readonly Collection $questions,
    ) {}
}
