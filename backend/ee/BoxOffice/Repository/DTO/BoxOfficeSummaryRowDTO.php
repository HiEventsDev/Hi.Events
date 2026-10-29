<?php

namespace HiEvents\Enterprise\BoxOffice\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BoxOfficeSummaryRowDTO extends BaseDataObject
{
    public function __construct(
        public string $tender,
        public string $operatorName,
        public string $day,
        public int $orders,
        public float $gross,
        public float $refunded,
    ) {}
}
