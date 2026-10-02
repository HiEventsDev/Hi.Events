<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BoxOfficeSessionReaderDTO extends BaseDataObject
{
    public function __construct(
        public int $id,
        public string $label,
    ) {}
}
