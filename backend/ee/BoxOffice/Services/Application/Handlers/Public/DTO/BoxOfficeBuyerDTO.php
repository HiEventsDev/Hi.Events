<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BoxOfficeBuyerDTO extends BaseDataObject
{
    public function __construct(
        public ?string $first_name = null,
        public ?string $last_name = null,
        public ?string $email = null,
    ) {}
}
