<?php

namespace HiEvents\Services\Domain\Tax\DTO;

class TaxAndProductAssociateParams
{
    public function __construct(
        public readonly int $productId,
        public readonly int $accountId,
        public readonly array $taxAndFeeIds,
    )
    {
    }
}
