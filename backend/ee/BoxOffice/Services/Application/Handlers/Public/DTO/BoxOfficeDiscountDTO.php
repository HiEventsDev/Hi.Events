<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BoxOfficeDiscountDTO extends BaseDataObject
{
    public function __construct(
        public string $type,
        public float $value,
    ) {}
}
