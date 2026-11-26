<?php

namespace HiEvents\Services\Domain\SelfService\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SelfServiceSubjectDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $locale,
    ) {}
}
