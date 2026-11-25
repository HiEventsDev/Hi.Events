<?php

namespace HiEvents\Services\Domain\SelfService\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SelfServiceSessionDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $token,
        public readonly string $expires_at,
        public readonly string $masked_email,
    ) {}
}
