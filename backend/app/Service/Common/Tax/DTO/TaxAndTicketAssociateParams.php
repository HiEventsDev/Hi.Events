<?php

namespace TicketKitten\Service\Common\Tax\DTO;

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
