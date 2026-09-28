<?php

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CashlessSalesPointStatsDTO extends BaseDataObject
{
    public function __construct(
        public int $salesPointId,
        public float $salesTotal,
        public int $transactionCount,
    ) {}
}
