<?php

namespace HiEvents\Services\Domain\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CashlessDailyStatsDTO extends BaseDataObject
{
    public function __construct(
        public string $date,
        public float $topped_up,
        public float $spent,
        public float $refunded,
    ) {}
}
