<?php

namespace HiEvents\Services\Domain\Tax\DTO;

class TaxAndTicketAssociateParams
{
    public function __construct(
        public readonly int $ticketId,
        public readonly int $accountId,
        public readonly array $taxAndFeeIds,
    )
    {
    }
}
