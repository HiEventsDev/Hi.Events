<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\BoxOfficeDomainObject;

class BoxOfficeWithPinDTO extends BaseDataObject
{
    public function __construct(
        public BoxOfficeDomainObject $box_office,
        public string $pin,
    ) {}
}
