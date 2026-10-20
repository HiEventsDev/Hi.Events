<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BoxOfficeStatsDTO extends BaseDataObject
{
    public function __construct(
        public string $currency,
        public int $orders,
        public float $gross,
        public float $refunded,
        public array $by_tender,
        public array $by_operator,
        public array $by_day,
    ) {}
}
