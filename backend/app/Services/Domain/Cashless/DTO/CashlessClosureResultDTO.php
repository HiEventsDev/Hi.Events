<?php

namespace HiEvents\Services\Domain\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CashlessClosureResultDTO extends BaseDataObject
{
    public function __construct(
        public int $wallets_closed,
        public float $amount_closed,
    ) {}
}
