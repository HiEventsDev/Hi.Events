<?php

namespace HiEvents\Services\Application\Handlers\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class RefundCashlessWalletResultDTO extends BaseDataObject
{
    public function __construct(
        public float $refunded_amount,
        public float $unrefundable_amount,
    ) {}
}
