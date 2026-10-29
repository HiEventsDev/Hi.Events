<?php

namespace HiEvents\Enterprise\BoxOffice\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BoxOfficeSalesCountDTO extends BaseDataObject
{
    public function __construct(
        public int $boxOfficeId,
        public int $salesCount,
        public float $grossSales,
    ) {}
}
