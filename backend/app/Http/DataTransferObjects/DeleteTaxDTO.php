<?php

namespace HiEvents\Http\DataTransferObjects;

use HiEvents\DataTransferObjects\BaseDTO;

class DeleteTaxDTO extends BaseDTO
{
    public function __construct(
        public readonly int $taxId,
        public readonly int $accountId,
    )
    {
    }
}
