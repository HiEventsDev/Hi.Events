<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CreatedBoxOfficeSessionDTO extends BaseDataObject
{
    public function __construct(
        public string $token,
        public BoxOfficeSessionDTO $session,
    ) {}
}
